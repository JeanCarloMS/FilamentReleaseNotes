<?php

declare(strict_types=1);

namespace JeanCarloMS\FilamentReleaseNotes\Domain\VO;

final readonly class GitHubRepositoryVO
{
    public function __construct(
        public string $owner,
        public string $repository,
        public string $baseUrl,
    ) {}

    public function commitUrl(string $sha): string
    {
        return rtrim($this->baseUrl, '/') . '/commit/' . $sha;
    }
}
