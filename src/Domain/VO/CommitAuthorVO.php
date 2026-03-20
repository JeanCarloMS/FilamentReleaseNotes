<?php

declare(strict_types=1);

namespace JeanCarloMS\FilamentReleaseNotes\Domain\VO;

final readonly class CommitAuthorVO
{
    public function __construct(
        public string $name,
        public ?string $email = null,
    ) {}
}
