<?php

declare(strict_types=1);

namespace JeanCarloMS\FilamentReleaseNotes\Infrastructure\Git;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use JeanCarloMS\FilamentReleaseNotes\Application\Contracts\ReleaseNotesReaderInterface;
use JeanCarloMS\FilamentReleaseNotes\Domain\VO\CommitAuthorVO;
use JeanCarloMS\FilamentReleaseNotes\Domain\VO\CommitEntryVO;
use JeanCarloMS\FilamentReleaseNotes\Domain\VO\ReleaseNotesFeedVO;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

final readonly class GitReleaseNotesReader implements ReleaseNotesReaderInterface
{
    public function __construct(
        private CacheFactory $cacheFactory,
        private ConfigRepository $config,
        private GitRemoteUrlParser $remoteUrlParser,
    ) {}

    public function read(
        ?string $repositoryPath = null,
        ?string $branch = null,
        ?int $page = null,
        ?int $perPage = null,
        ?string $search = null,
    ): ReleaseNotesFeedVO
    {
        $resolvedRepositoryPath = $repositoryPath ?? (string) $this->config->get('filament-release-notes.repository_path', base_path());
        $resolvedPage = max(1, $page ?? 1);
        $resolvedPerPage = max(1, $perPage ?? (int) $this->config->get('filament-release-notes.default_per_page', 100));
        $resolvedSearch = filled($search) ? trim((string) $search) : null;
        $cacheStore = $this->config->get('filament-release-notes.cache_store');

        $cacheRepository = $cacheStore
            ? $this->cacheFactory->store((string) $cacheStore)
            : $this->cacheFactory->store();

        $repositoryValidationError = $this->validateRepository(
            repositoryPath: $resolvedRepositoryPath,
            page: $resolvedPage,
            perPage: $resolvedPerPage,
            search: $resolvedSearch,
        );

        if ($repositoryValidationError !== null) {
            return $repositoryValidationError;
        }

        try {
            $hasCommits = $this->repositoryHasCommits($resolvedRepositoryPath);
            $resolvedBranch = $branch ?? $this->resolveBranch($resolvedRepositoryPath);
            $headSha = $hasCommits
                ? $this->resolveHeadSha($resolvedRepositoryPath, $resolvedBranch)
                : null;
        } catch (ProcessFailedException $exception) {
            return $this->errorFeed(
                page: $resolvedPage,
                perPage: $resolvedPerPage,
                search: $resolvedSearch,
                branch: $branch,
                errorMessage: $this->resolveGitErrorMessage($exception, $resolvedRepositoryPath),
            );
        }

        $cacheKey = $this->cacheKey(
            $resolvedRepositoryPath,
            $resolvedBranch,
            $resolvedPage,
            $resolvedPerPage,
            $resolvedSearch,
            $headSha,
        );
        $cachedFeed = $cacheRepository->get($cacheKey);

        if ($cachedFeed instanceof ReleaseNotesFeedVO) {
            return $cachedFeed;
        }

        $feed = $this->readUncached(
            repositoryPath: $resolvedRepositoryPath,
            branch: $resolvedBranch,
            page: $resolvedPage,
            perPage: $resolvedPerPage,
            search: $resolvedSearch,
            headSha: $headSha,
            hasCommits: $hasCommits,
        );

        if ($feed->hasError) {
            return $feed;
        }

        $cacheRepository->put(
            $cacheKey,
            $feed,
            (int) $this->config->get('filament-release-notes.cache_ttl', 300),
        );

        return $feed;
    }

    private function readUncached(
        string $repositoryPath,
        ?string $branch,
        int $page,
        int $perPage,
        ?string $search,
        ?string $headSha,
        bool $hasCommits,
    ): ReleaseNotesFeedVO
    {
        try {
            $remoteRepository = $this->remoteUrlParser->parse(
                $this->runGitCommand($repositoryPath, ['config', '--get', 'remote.origin.url'], allowFailure: true),
            );

            if (! $hasCommits) {
                return new ReleaseNotesFeedVO(
                    commits: [],
                    repository: $remoteRepository,
                    branch: $branch,
                    headSha: null,
                    page: $page,
                    perPage: $perPage,
                    total: 0,
                    search: $search,
                );
            }

            $total = $this->resolveTotalCount(
                repositoryPath: $repositoryPath,
                branch: $branch,
                search: $search,
            );
            $effectivePage = min($page, max(1, (int) ceil($total / max(1, $perPage))));

            return new ReleaseNotesFeedVO(
                commits: $this->resolveCommits(
                    repositoryPath: $repositoryPath,
                    branch: $branch,
                    page: $effectivePage,
                    perPage: $perPage,
                    search: $search,
                    commitUrlResolver: $remoteRepository
                        ? fn (string $sha): string => $remoteRepository->commitUrl($sha)
                        : null,
                ),
                repository: $remoteRepository,
                branch: $branch,
                headSha: $headSha,
                page: $effectivePage,
                perPage: $perPage,
                total: $total,
                search: $search,
            );
        } catch (ProcessFailedException $exception) {
            return $this->errorFeed(
                page: $page,
                perPage: $perPage,
                search: $search,
                branch: $branch,
                headSha: $headSha,
                errorMessage: $this->resolveGitErrorMessage($exception, $repositoryPath),
            );
        }
    }

    /**
     * @param  (callable(string): string)|null  $commitUrlResolver
     * @return list<CommitEntryVO>
     */
    private function resolveCommits(
        string $repositoryPath,
        ?string $branch,
        int $page,
        int $perPage,
        ?string $search,
        ?callable $commitUrlResolver = null,
    ): array
    {
        $format = '%H%x1f%h%x1f%an%x1f%ae%x1f%aI%x1f%B%x1e';
        $arguments = [
            'log',
            '--max-count=' . $perPage,
            '--skip=' . (($page - 1) * $perPage),
            '--date=iso-strict',
            '--fixed-strings',
            '--regexp-ignore-case',
            '--pretty=format:' . $format,
        ];

        if (filled($branch)) {
            $arguments[] = $branch;
        }

        if (filled($search)) {
            $arguments[] = '--grep=' . $search;
        }

        $output = $this->runGitCommand($repositoryPath, $arguments);

        if ($output === '') {
            return [];
        }

        $entries = array_filter(explode("\x1e", $output));

        return array_values(array_map(function (string $entry) use ($commitUrlResolver): CommitEntryVO {
            [$sha, $shortSha, $authorName, $authorEmail, $committedAt, $message] = array_pad(
                explode("\x1f", trim($entry)),
                6,
                null,
            );

            [$subject, $body] = $this->splitCommitMessage((string) $message);
            [$type, $scope] = $this->resolveCommitType($subject ?? '');

            return new CommitEntryVO(
                sha: (string) $sha,
                shortSha: (string) $shortSha,
                subject: $subject,
                body: $body,
                author: new CommitAuthorVO(
                    name: (string) $authorName,
                    email: filled($authorEmail) ? (string) $authorEmail : null,
                ),
                committedAt: CarbonImmutable::parse((string) $committedAt),
                type: $type,
                scope: $scope,
                commitUrl: $commitUrlResolver ? $commitUrlResolver((string) $sha) : null,
            );
        }, $entries));
    }

    /**
     * @return array{0:string,1:string|null}
     */
    private function splitCommitMessage(string $message): array
    {
        $normalizedMessage = str_replace(["\r\n", "\r"], "\n", rtrim($message));

        if ($normalizedMessage === '') {
            return ['', null];
        }

        [$subject, $body] = array_pad(explode("\n", $normalizedMessage, 2), 2, null);

        return [
            trim($subject),
            filled(trim((string) $body)) ? trim((string) $body) : null,
        ];
    }

    /**
     * @return array{0:string,1:string|null}
     */
    private function resolveCommitType(string $subject): array
    {
        if (preg_match('/^(?<type>feat|fix|docs|chore|refactor|perf|test)(?:\((?<scope>[^)]+)\))?:/i', $subject, $matches) === 1) {
            return [
                match (strtolower($matches['type'])) {
                    'feat' => 'feature',
                    'fix' => 'fix',
                    'docs' => 'docs',
                    default => 'chore',
                },
                $matches['scope'] ?? null,
            ];
        }

        return ['chore', null];
    }

    private function resolveTotalCount(string $repositoryPath, ?string $branch, ?string $search): int
    {
        $arguments = ['rev-list', '--count'];

        if (filled($search)) {
            $arguments[] = '--fixed-strings';
            $arguments[] = '--regexp-ignore-case';
            $arguments[] = '--grep=' . $search;
        }

        $arguments[] = $branch ?: 'HEAD';

        $total = $this->runGitCommand($repositoryPath, $arguments);

        return is_numeric($total) ? (int) $total : 0;
    }

    private function resolveHeadSha(string $repositoryPath, ?string $branch): ?string
    {
        $arguments = ['rev-parse'];

        if (filled($branch)) {
            $arguments[] = $branch;
        } else {
            $arguments[] = 'HEAD';
        }

        $sha = $this->runGitCommand($repositoryPath, $arguments);

        return $sha !== '' ? $sha : null;
    }

    private function resolveBranch(string $repositoryPath): ?string
    {
        $branch = $this->runGitCommand($repositoryPath, ['branch', '--show-current']);

        return $branch !== '' ? $branch : null;
    }

    private function validateRepository(
        string $repositoryPath,
        int $page,
        int $perPage,
        ?string $search,
    ): ?ReleaseNotesFeedVO
    {
        try {
            $isInsideWorkTree = $this->runGitCommand($repositoryPath, ['rev-parse', '--is-inside-work-tree']);
        } catch (ProcessFailedException $exception) {
            return $this->errorFeed(
                page: $page,
                perPage: $perPage,
                search: $search,
                errorMessage: $this->resolveGitErrorMessage($exception, $repositoryPath),
            );
        }

        if ($isInsideWorkTree === 'true') {
            return null;
        }

        return $this->errorFeed(
            page: $page,
            perPage: $perPage,
            search: $search,
            errorMessage: sprintf(
                'La ruta configurada no apunta a un repositorio Git de trabajo válido: %s',
                $repositoryPath,
            ),
        );
    }

    private function repositoryHasCommits(string $repositoryPath): bool
    {
        return (int) $this->runGitCommand($repositoryPath, ['rev-list', '--count', '--all']) > 0;
    }

    /**
     * @param  list<string>  $arguments
     */
    private function runGitCommand(string $repositoryPath, array $arguments, bool $allowFailure = false): string
    {
        $process = new Process([
            (string) $this->config->get('filament-release-notes.git_binary', 'git'),
            '-c',
            'safe.directory=' . $repositoryPath,
            '-C',
            $repositoryPath,
            ...$arguments,
        ]);

        $process->run();

        if (! $process->isSuccessful()) {
            if ($allowFailure) {
                return '';
            }

            throw new ProcessFailedException($process);
        }

        return trim($process->getOutput());
    }

    private function errorFeed(
        int $page,
        int $perPage,
        ?string $search,
        ?string $branch = null,
        ?string $headSha = null,
        string $errorMessage = 'No fue posible leer el historial Git.',
    ): ReleaseNotesFeedVO
    {
        return new ReleaseNotesFeedVO(
            commits: [],
            repository: null,
            branch: $branch,
            headSha: $headSha,
            page: $page,
            perPage: $perPage,
            total: 0,
            search: $search,
            hasError: true,
            errorMessage: $errorMessage,
        );
    }

    private function resolveGitErrorMessage(ProcessFailedException $exception, string $repositoryPath): string
    {
        $process = $exception->getProcess();
        $rawMessage = trim($process->getErrorOutput());

        if ($rawMessage === '') {
            $rawMessage = trim($process->getOutput());
        }

        $normalizedMessage = strtolower($rawMessage);

        if (str_contains($normalizedMessage, 'dubious ownership')) {
            return $this->appendGitDetails(
                'Git rechazó el repositorio por ownership. El proceso web probablemente corre con otro usuario distinto al dueño del repo.',
                $rawMessage,
            );
        }

        if (str_contains($normalizedMessage, 'not a git repository')) {
            return $this->appendGitDetails(
                sprintf(
                    'La ruta configurada no apunta a un repositorio Git válido: %s',
                    $repositoryPath,
                ),
                $rawMessage,
            );
        }

        if (str_contains($normalizedMessage, 'cannot change to')) {
            return $this->appendGitDetails(
                'Git no pudo acceder al repositorio configurado. Posible problema de ruta, ownership o permisos del directorio.',
                $rawMessage,
            );
        }

        if (str_contains($normalizedMessage, 'git: not found')) {
            return $this->appendGitDetails(
                'No fue posible ejecutar Git en el servidor. Verifica que el binario de Git esté instalado y disponible para el proceso PHP.',
                $rawMessage,
            );
        }

        return $this->appendGitDetails(
            'No fue posible leer el historial Git del repositorio configurado.',
            $rawMessage,
        );
    }

    private function appendGitDetails(string $message, string $rawMessage): string
    {
        if ($rawMessage === '') {
            return $message;
        }

        return $message . ' Detalle: ' . preg_replace('/\s+/', ' ', $rawMessage);
    }

    private function cacheKey(
        string $repositoryPath,
        ?string $branch,
        int $page,
        int $perPage,
        ?string $search,
        ?string $headSha,
    ): string
    {
        return sprintf(
            'filament_release_notes:%s:%s:%d:%d:%s:%s',
            sha1($repositoryPath),
            $branch ?? 'head',
            $page,
            $perPage,
            sha1((string) $search),
            $headSha ?? 'unknown',
        );
    }
}
