<?php

declare(strict_types=1);

namespace Ros\FilamentReleaseNotes\Support;

use Filament\Facades\Filament;
use Ros\FilamentReleaseNotes\Domain\VO\ReleaseNotesPluginConfigVO;
use Throwable;

final class ReleaseNotesPluginRegistry
{
    /**
     * @var array<string, ReleaseNotesPluginConfigVO>
     */
    private array $configsByPanel = [];

    public function register(string $panelId, ReleaseNotesPluginConfigVO $config): void
    {
        $this->configsByPanel[$panelId] = $config;
    }

    public function forPanel(string $panelId): ReleaseNotesPluginConfigVO
    {
        return $this->configsByPanel[$panelId]
            ?? $this->configsByPanel[array_key_first($this->configsByPanel)]
            ?? new ReleaseNotesPluginConfigVO(
                navigationGroup: 'System',
                navigationSort: 90,
                navigationIcon: 'heroicon-o-megaphone',
                slug: 'release-notes',
                title: 'Release Notes',
                heading: 'Release Notes',
                subheading: 'Timeline of the latest changes deployed to this project.',
                defaultPerPage: (int) config('filament-release-notes.default_per_page', 100),
                perPageOptions: (array) config('filament-release-notes.per_page_options', [25, 50, 100, 150, 200]),
                repositoryPath: config('filament-release-notes.repository_path'),
                branch: config('filament-release-notes.branch'),
            );
    }

    public function forCurrentPanel(): ReleaseNotesPluginConfigVO
    {
        try {
            return $this->forPanel(Filament::getCurrentPanel()->getId());
        } catch (Throwable) {
            return $this->forPanel(array_key_first($this->configsByPanel) ?? 'default');
        }
    }
}
