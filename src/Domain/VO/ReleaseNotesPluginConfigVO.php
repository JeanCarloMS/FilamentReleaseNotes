<?php

declare(strict_types=1);

namespace Ros\FilamentReleaseNotes\Domain\VO;

final readonly class ReleaseNotesPluginConfigVO
{
    public function __construct(
        public string $navigationGroup,
        public int $navigationSort,
        public string $navigationIcon,
        public string $slug,
        public string $title,
        public string $heading,
        public string $subheading,
        public int $defaultPerPage,
        public array $perPageOptions,
        public ?string $repositoryPath = null,
        public ?string $branch = null,
    ) {}
}
