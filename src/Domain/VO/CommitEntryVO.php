<?php

declare(strict_types=1);

namespace jeancarloms\FilamentReleaseNotes\Domain\VO;

use Carbon\CarbonImmutable;

final readonly class CommitEntryVO
{
    public function __construct(
        public string $sha,
        public string $shortSha,
        public string $subject,
        public ?string $body,
        public CommitAuthorVO $author,
        public CarbonImmutable $committedAt,
        public string $type,
        public ?string $scope,
        public ?string $commitUrl = null,
    ) {}
}
