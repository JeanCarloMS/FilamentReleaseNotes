<?php

declare(strict_types=1);

namespace Ros\FilamentReleaseNotes\Pages;

use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Ros\FilamentReleaseNotes\Application\Services\LoadReleaseNotesService;
use Ros\FilamentReleaseNotes\Support\ReleaseNotesPluginRegistry;

final class ReleaseNotes extends Page
{
    use HasPageShield;

    protected static string $view = 'filament-release-notes::pages.release-notes';

    /**
     * @var array<string, mixed>
     */
    public array $releaseNotes = [];

    public string $search = '';

    public int $page = 1;

    public int $perPage = 100;

    public int $defaultPerPage = 100;

    /**
     * @var list<int>
     */
    public array $perPageOptions = [];

    public function mount(LoadReleaseNotesService $loadReleaseNotesService, ReleaseNotesPluginRegistry $pluginRegistry): void
    {
        $config = $pluginRegistry->forCurrentPanel();

        $this->perPageOptions = $config->perPageOptions;
        $this->defaultPerPage = $config->defaultPerPage;
        $this->search = trim((string) request()->query('search', ''));
        $this->page = max(1, (int) request()->integer('page', 1));
        $requestedPerPage = (int) request()->integer('per_page', $config->defaultPerPage);
        $this->perPage = in_array($requestedPerPage, $this->perPageOptions, true)
            ? $requestedPerPage
            : $config->defaultPerPage;

        $this->loadReleaseNotes($loadReleaseNotesService, $config->repositoryPath, $config->branch);
    }

    public static function getSlug(): string
    {
        return app(ReleaseNotesPluginRegistry::class)->forCurrentPanel()->slug;
    }

    public static function getNavigationIcon(): string
    {
        return app(ReleaseNotesPluginRegistry::class)->forCurrentPanel()->navigationIcon;
    }

    public static function getNavigationGroup(): ?string
    {
        return app(ReleaseNotesPluginRegistry::class)->forCurrentPanel()->navigationGroup;
    }

    public static function getNavigationSort(): ?int
    {
        return app(ReleaseNotesPluginRegistry::class)->forCurrentPanel()->navigationSort;
    }

    public function getTitle(): string
    {
        return app(ReleaseNotesPluginRegistry::class)->forCurrentPanel()->title;
    }

    public function getHeading(): string
    {
        return app(ReleaseNotesPluginRegistry::class)->forCurrentPanel()->heading;
    }

    public function getSubheading(): ?string
    {
        return app(ReleaseNotesPluginRegistry::class)->forCurrentPanel()->subheading;
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('repository')
                ->label('Abrir repositorio')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url($this->releaseNotes['repositoryUrl'] ?? null, shouldOpenInNewTab: true)
                ->visible(filled($this->releaseNotes['repositoryUrl'] ?? null)),
            Action::make('refresh')
                ->label('Actualizar')
                ->icon('heroicon-o-arrow-path')
                ->action(function (LoadReleaseNotesService $loadReleaseNotesService, ReleaseNotesPluginRegistry $pluginRegistry): void {
                    $config = $pluginRegistry->forCurrentPanel();

                    $this->loadReleaseNotes($loadReleaseNotesService, $config->repositoryPath, $config->branch);
                }),
        ];
    }

    private function loadReleaseNotes(LoadReleaseNotesService $loadReleaseNotesService, ?string $repositoryPath, ?string $branch): void
    {
        $this->releaseNotes = $loadReleaseNotesService->handle(
            repositoryPath: $repositoryPath,
            branch: $branch,
            page: $this->page,
            perPage: $this->perPage,
            search: $this->search !== '' ? $this->search : null,
        );

        $this->page = (int) ($this->releaseNotes['filters']['page'] ?? $this->page);
    }
}
