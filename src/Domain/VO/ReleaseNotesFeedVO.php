<?php

declare(strict_types=1);

namespace JeanCarloMS\FilamentReleaseNotes\Domain\VO;

final readonly class ReleaseNotesFeedVO
{
    /**
     * @param  list<CommitEntryVO>  $commits
     */
    public function __construct(
        public array $commits,
        public ?GitHubRepositoryVO $repository,
        public ?string $branch,
        public ?string $headSha,
        public int $page,
        public int $perPage,
        public int $total,
        public ?string $search = null,
        public bool $hasError = false,
        public ?string $errorMessage = null,
    ) {}
}
