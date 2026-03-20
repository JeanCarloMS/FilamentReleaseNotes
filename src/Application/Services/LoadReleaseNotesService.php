<?php

declare(strict_types=1);

namespace Ros\FilamentReleaseNotes\Application\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Ros\FilamentReleaseNotes\Application\Contracts\ReleaseNotesReaderInterface;

final readonly class LoadReleaseNotesService
{
    public function __construct(
        private ReleaseNotesReaderInterface $reader,
    ) {}

    /**
     * @return array{
     *     summary: array{total:int, features:int, fixes:int, docs:int, chores:int},
     *     branch: string|null,
     *     headSha: string|null,
     *     repositoryUrl: string|null,
     *     filters: array{search:string|null, page:int, perPage:int},
     *     pagination: array{currentPage:int, perPage:int, total:int, totalPages:int, from:int, to:int, hasPreviousPage:bool, hasNextPage:bool},
     *     commits: array<int, array<string, mixed>>,
     *     groupedCommits: array<int, array{label:string, commits: array<int, array<string, mixed>>}>
     * }
     */
    public function handle(
        ?string $repositoryPath = null,
        ?string $branch = null,
        ?int $page = null,
        ?int $perPage = null,
        ?string $search = null,
    ): array
    {
        $feed = $this->reader->read(
            repositoryPath: $repositoryPath,
            branch: $branch,
            page: $page,
            perPage: $perPage,
            search: $search,
        );

        $commits = collect($feed->commits)
            ->map(fn ($commit): array => [
                'sha' => $commit->sha,
                'short_sha' => $commit->shortSha,
                'subject' => $commit->subject,
                'body' => $commit->body,
                'author_name' => $commit->author->name,
                'author_email' => $commit->author->email,
                'committed_at' => $commit->committedAt,
                'committed_at_human' => $commit->committedAt->diffForHumans(),
                'committed_at_label' => $commit->committedAt->translatedFormat((string) config('filament-release-notes.date_format', 'M d, Y \a\t H:i')),
                'type' => $commit->type,
                'scope' => $commit->scope,
                'commit_url' => $commit->commitUrl,
                'badge' => $this->resolveBadge($commit->type),
            ]);

        return [
            'summary' => [
                'total' => $feed->total,
                'features' => $commits->where('type', 'feature')->count(),
                'fixes' => $commits->where('type', 'fix')->count(),
                'docs' => $commits->where('type', 'docs')->count(),
                'chores' => $commits->where('type', 'chore')->count(),
            ],
            'branch' => $feed->branch,
            'headSha' => $feed->headSha,
            'repositoryUrl' => $feed->repository?->baseUrl,
            'filters' => [
                'search' => $feed->search,
                'page' => $feed->page,
                'perPage' => $feed->perPage,
            ],
            'pagination' => $this->buildPagination($feed->page, $feed->perPage, $feed->total),
            'commits' => $commits->all(),
            'groupedCommits' => $this->groupCommitsByDay($commits),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $commits
     * @return array<int, array{label:string, commits: array<int, array<string, mixed>>}>
     */
    private function groupCommitsByDay(Collection $commits): array
    {
        return $commits
            ->groupBy(fn (array $commit): string => $commit['committed_at']->format('Y-m-d'))
            ->map(function (Collection $dayCommits, string $dateKey): array {
                return [
                    'label' => CarbonImmutable::parse($dateKey)->translatedFormat('l, d \d\e F \d\e Y'),
                    'commits' => $dayCommits->values()->all(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{label:string, classes:string}
     */
    private function resolveBadge(string $type): array
    {
        return match ($type) {
            'feature' => [
                'label' => 'Feature',
                'classes' => 'border border-emerald-200 bg-emerald-50 text-emerald-700',
            ],
            'fix' => [
                'label' => 'Fix',
                'classes' => 'border border-amber-200 bg-amber-50 text-amber-700',
            ],
            'docs' => [
                'label' => 'Docs',
                'classes' => 'border border-sky-200 bg-sky-50 text-sky-700',
            ],
            default => [
                'label' => 'Chore',
                'classes' => 'border border-slate-200 bg-slate-50 text-slate-600',
            ],
        };
    }

    /**
     * @return array{currentPage:int, perPage:int, total:int, totalPages:int, from:int, to:int, hasPreviousPage:bool, hasNextPage:bool}
     */
    private function buildPagination(int $page, int $perPage, int $total): array
    {
        $totalPages = max(1, (int) ceil($total / max(1, $perPage)));
        $currentPage = min($page, $totalPages);
        $from = $total === 0 ? 0 : (($currentPage - 1) * $perPage) + 1;
        $to = min($total, $currentPage * $perPage);

        return [
            'currentPage' => $currentPage,
            'perPage' => $perPage,
            'total' => $total,
            'totalPages' => $totalPages,
            'from' => $from,
            'to' => $to,
            'hasPreviousPage' => $currentPage > 1,
            'hasNextPage' => $currentPage < $totalPages,
        ];
    }
}
