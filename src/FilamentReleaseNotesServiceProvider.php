<?php

declare(strict_types=1);

namespace JeanCarloMS\FilamentReleaseNotes;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Support\ServiceProvider;
use JeanCarloMS\FilamentReleaseNotes\Application\Contracts\ReleaseNotesReaderInterface;
use JeanCarloMS\FilamentReleaseNotes\Application\Services\LoadReleaseNotesService;
use JeanCarloMS\FilamentReleaseNotes\Infrastructure\Git\GitReleaseNotesReader;
use JeanCarloMS\FilamentReleaseNotes\Infrastructure\Git\GitRemoteUrlParser;
use JeanCarloMS\FilamentReleaseNotes\Support\ReleaseNotesPluginRegistry;

class FilamentReleaseNotesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'filament-release-notes');

        $this->publishes([
            __DIR__ . '/../config/filament-release-notes.php' => config_path('filament-release-notes.php'),
        ], 'filament-release-notes-config');

        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/filament-release-notes'),
        ], 'filament-release-notes-views');
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/filament-release-notes.php', 'filament-release-notes');

        $this->app->singleton(ReleaseNotesPluginRegistry::class);
        $this->app->singleton(GitRemoteUrlParser::class);

        $this->app->singleton(ReleaseNotesReaderInterface::class, function ($app): ReleaseNotesReaderInterface {
            return new GitReleaseNotesReader(
                cacheFactory: $app->make(CacheFactory::class),
                config: $app->make(ConfigRepository::class),
                remoteUrlParser: $app->make(GitRemoteUrlParser::class),
            );
        });

        $this->app->singleton(LoadReleaseNotesService::class);
    }
}
