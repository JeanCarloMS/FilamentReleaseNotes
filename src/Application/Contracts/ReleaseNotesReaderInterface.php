<?php

declare(strict_types=1);

namespace JeanCarloMS\FilamentReleaseNotes\Application\Contracts;

use JeanCarloMS\FilamentReleaseNotes\Domain\VO\ReleaseNotesFeedVO;

interface ReleaseNotesReaderInterface
{
    public function read(
        ?string $repositoryPath = null,
        ?string $branch = null,
        ?int $page = null,
        ?int $perPage = null,
        ?string $search = null,
    ): ReleaseNotesFeedVO;
}
