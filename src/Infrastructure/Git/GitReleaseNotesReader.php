<?php

declare(strict_types=1);

namespace Ros\FilamentReleaseNotes\Infrastructure\Git;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Ros\FilamentReleaseNotes\Application\Contracts\ReleaseNotesReaderInterface;
use Ros\FilamentReleaseNotes\Domain\VO\CommitAuthorVO;
use Ros\FilamentReleaseNotes\Domain\VO\CommitEntryVO;
use Ros\FilamentReleaseNotes\Domain\VO\ReleaseNotesFeedVO;
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
        $resolvedBranch = $branch ?? $this->resolveBranch($resolvedRepositoryPath);
        $resolvedPage = max(1, $page ?? 1);
        $resolvedPerPage = max(1, $perPage ?? (int) $this->config->get('filament-release-notes.default_per_page', 100));
        $resolvedSearch = filled($search) ? trim((string) $search) : null;
        $headSha = $this->resolveHeadSha($resolvedRepositoryPath, $resolvedBranch);
        $cacheStore = $this->config->get('filament-release-notes.cache_store');

        $cacheRepository = $cacheStore
            ? $this->cacheFactory->store((string) $cacheStore)
            : $this->cacheFactory->store();

        return $cacheRepository->remember(
            $this->cacheKey($resolvedRepositoryPath, $resolvedBranch, $resolvedPage, $resolvedPerPage, $resolvedSearch, $headSha),
            (int) $this->config->get('filament-release-notes.cache_ttl', 300),
            function () use ($resolvedRepositoryPath, $resolvedBranch, $resolvedPage, $resolvedPerPage, $resolvedSearch, $headSha): ReleaseNotesFeedVO {
                $remoteRepository = $this->remoteUrlParser->parse(
                    $this->runGitCommand($resolvedRepositoryPath, ['config', '--get', 'remote.origin.url'], allowFailure: true),
                );
                $total = $this->resolveTotalCount(
                    repositoryPath: $resolvedRepositoryPath,
                    branch: $resolvedBranch,
                    search: $resolvedSearch,
                );
                $effectivePage = min($resolvedPage, max(1, (int) ceil($total / max(1, $resolvedPerPage))));

                return new ReleaseNotesFeedVO(
                    commits: $this->resolveCommits(
                        repositoryPath: $resolvedRepositoryPath,
                        branch: $resolvedBranch,
                        page: $effectivePage,
                        perPage: $resolvedPerPage,
                        search: $resolvedSearch,
                        commitUrlResolver: $remoteRepository
                            ? fn (string $sha): string => $remoteRepository->commitUrl($sha)
                            : null,
                    ),
                    repository: $remoteRepository,
                    branch: $resolvedBranch,
                    headSha: $headSha,
                    page: $effectivePage,
                    perPage: $resolvedPerPage,
                    total: $total,
                    search: $resolvedSearch,
                );
            },
        );
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
        $format = '%H%x1f%h%x1f%an%x1f%ae%x1f%aI%x1f%s%x1f%b%x1e';
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

        $output = $this->runGitCommand($repositoryPath, $arguments, allowFailure: true);

        if ($output === '') {
            return [];
        }

        $entries = array_filter(explode("\x1e", $output));

        return array_values(array_map(function (string $entry) use ($commitUrlResolver): CommitEntryVO {
            [$sha, $shortSha, $authorName, $authorEmail, $committedAt, $subject, $body] = array_pad(
                explode("\x1f", trim($entry)),
                7,
                null,
            );

            [$type, $scope] = $this->resolveCommitType($subject ?? '');

            return new CommitEntryVO(
                sha: (string) $sha,
                shortSha: (string) $shortSha,
                subject: (string) $subject,
                body: filled(trim((string) $body)) ? trim((string) $body) : null,
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

        $total = $this->runGitCommand($repositoryPath, $arguments, allowFailure: true);

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

        $sha = $this->runGitCommand($repositoryPath, $arguments, allowFailure: true);

        return $sha !== '' ? $sha : null;
    }

    private function resolveBranch(string $repositoryPath): ?string
    {
        $branch = $this->runGitCommand($repositoryPath, ['branch', '--show-current'], allowFailure: true);

        return $branch !== '' ? $branch : null;
    }

    /**
     * @param  list<string>  $arguments
     */
    private function runGitCommand(string $repositoryPath, array $arguments, bool $allowFailure = false): string
    {
        $process = new Process([
            (string) $this->config->get('filament-release-notes.git_binary', 'git'),
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
