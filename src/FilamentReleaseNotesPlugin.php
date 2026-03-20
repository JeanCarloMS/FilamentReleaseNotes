<?php

declare(strict_types=1);

namespace JeanCarloMS\FilamentReleaseNotes;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use JeanCarloMS\FilamentReleaseNotes\Domain\VO\ReleaseNotesPluginConfigVO;
use JeanCarloMS\FilamentReleaseNotes\Pages\ReleaseNotes;
use JeanCarloMS\FilamentReleaseNotes\Support\ReleaseNotesPluginRegistry;
use Spatie\Permission\Contracts\Permission as PermissionContract;
use Spatie\Permission\PermissionRegistrar;

class FilamentReleaseNotesPlugin implements Plugin
{
    private string $navigationGroup = 'System';

    private int $navigationSort = 90;

    private string $navigationIcon = 'heroicon-o-megaphone';

    private string $slug = 'release-notes';

    private string $title = 'Release Notes';

    private string $heading = 'Release Notes';

    private string $subheading = 'Follow the latest changes delivered to this project.';

    private ?int $defaultPerPage = null;

    /**
     * @var list<int>|null
     */
    private ?array $perPageOptions = null;

    private ?string $repositoryPath = null;

    private ?string $branch = null;

    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'filament-release-notes';
    }

    public function register(Panel $panel): void
    {
        app(ReleaseNotesPluginRegistry::class)->register(
            $panel->getId(),
            new ReleaseNotesPluginConfigVO(
                navigationGroup: $this->navigationGroup,
                navigationSort: $this->navigationSort,
                navigationIcon: $this->navigationIcon,
                slug: $this->slug,
                title: $this->title,
                heading: $this->heading,
                subheading: $this->subheading,
                defaultPerPage: $this->defaultPerPage ?? (int) config('filament-release-notes.default_per_page', 100),
                perPageOptions: $this->perPageOptions ?? (array) config('filament-release-notes.per_page_options', [25, 50, 100, 150, 200]),
                repositoryPath: $this->repositoryPath ?? config('filament-release-notes.repository_path'),
                branch: $this->branch ?? config('filament-release-notes.branch'),
            ),
        );

        $panel->pages([
            ReleaseNotes::class,
        ]);
    }

    public function boot(Panel $panel): void
    {
        $this->ensurePermissionExists($panel);
    }

    public function navigationGroup(string $navigationGroup): static
    {
        $this->navigationGroup = $navigationGroup;

        return $this;
    }

    public function navigationSort(int $navigationSort): static
    {
        $this->navigationSort = $navigationSort;

        return $this;
    }

    public function navigationIcon(string $navigationIcon): static
    {
        $this->navigationIcon = $navigationIcon;

        return $this;
    }

    public function slug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function title(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function heading(string $heading): static
    {
        $this->heading = $heading;

        return $this;
    }

    public function subheading(string $subheading): static
    {
        $this->subheading = $subheading;

        return $this;
    }

    public function defaultPerPage(int $defaultPerPage): static
    {
        $this->defaultPerPage = $defaultPerPage;

        return $this;
    }

    /**
     * @param  list<int>  $perPageOptions
     */
    public function perPageOptions(array $perPageOptions): static
    {
        $this->perPageOptions = array_values(array_unique(array_map('intval', $perPageOptions)));

        return $this;
    }

    public function maxCommits(int $maxCommits): static
    {
        return $this->defaultPerPage($maxCommits);
    }

    public function repositoryPath(?string $repositoryPath): static
    {
        $this->repositoryPath = $repositoryPath;

        return $this;
    }

    public function branch(?string $branch): static
    {
        $this->branch = $branch;

        return $this;
    }

    private function ensurePermissionExists(Panel $panel): void
    {
        if (! Schema::hasTable(config('permission.table_names.permissions', 'permissions'))) {
            return;
        }

        /** @var class-string<Model&PermissionContract>|null $permissionModel */
        $permissionModel = config('permission.models.permission');

        if (! is_string($permissionModel) || ! class_exists($permissionModel)) {
            return;
        }

        $permission = $permissionModel::query()->firstOrCreate([
            'name' => 'page_ReleaseNotes',
            'guard_name' => $panel->getAuthGuard(),
        ]);

        if ($permission->wasRecentlyCreated) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
}
