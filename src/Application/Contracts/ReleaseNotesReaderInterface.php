<?php

declare(strict_types=1);

namespace jeancarloms\FilamentReleaseNotes\Application\Contracts;

use jeancarloms\FilamentReleaseNotes\Domain\VO\ReleaseNotesFeedVO;

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
