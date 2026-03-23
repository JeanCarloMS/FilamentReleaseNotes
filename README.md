# JeanCarloMS Filament Release Notes

Filament plugin that displays a timeline of commits from the current Git repository as a "Release Notes" page.

Includes:

- real pagination over `git log`
- commit message keyword search
- direct links to commits on GitHub
- `Filament Shield` integration
- per-panel configuration

## Requirements

- PHP 8.2+
- Laravel 12+
- Filament 3.3+
- `bezhansalleh/filament-shield`
- local access to the project's Git repository

## Installation

```bash
composer require JeanCarloMS/filament-release-notes
```

## Git Safe Directory
If you receive this error in your app it is because the owner of the directory and the PHP of the web server are differents
> No fue posible leer el historial Git. Git rechazó el repositorio por ownership. El proceso web probablemente corre con otro usuario distinto al dueño del repo. Detalle: fatal: detected dubious ownership in repository at '/var/www/html/ros' To add an exception for this directory, call: git config --global --add safe.directory /var/www/html/ros
Solution, run this command: 
```text
That command tells Git, at the system-wide level, to trust that directory even if the repository owner does not match the user running the command.
```
```bash
sudo git config --system --add safe.directory /var/www/html/your_project
```
If you want to revert:
```bash
sudo git config --system --unset-all safe.directory /var/www/html/your_project
```

## Custom Filament Theme

If the plugin will be used inside a Filament panel, you must create a custom theme for that panel so Tailwind can include the plugin styles.

Add the following line to that theme's CSS file:

```css
@source '../../../../vendor/jeancarloms/filament-release-notes/resources/views/**/*.blade.php';
```

This ensures the plugin styles are loaded correctly in the panel.

## Publish Configuration

```bash
php artisan vendor:publish --tag=filament-release-notes-config
```

## Register The Plugin In Filament

```php
use JeanCarloMS\FilamentReleaseNotes\FilamentReleaseNotesPlugin;

FilamentReleaseNotesPlugin::make()
    ->navigationGroup('System')
    ->navigationSort(11)
    ->navigationIcon('heroicon-o-sparkles')
    ->slug('release-notes')
    ->title('Release Notes')
    ->heading('Release Notes')
    ->subheading('Browse the visual history of changes in the current repository.')
    ->defaultPerPage(100)
    ->perPageOptions([25, 50, 100, 150, 200])
    ->repositoryPath(base_path())
```

## Shield

The page uses `HasPageShield`, so the required permission is:

```text
page_ReleaseNotes
```

The plugin will attempt to create that permission automatically if the `permissions` table exists.

## Configuration

File: `config/filament-release-notes.php`

Available options:

- `git_binary`
- `repository_path`
- `branch`
- `default_per_page`
- `per_page_options`
- `cache_store`
- `cache_ttl`
- `date_format`

## Limitations

- search only runs against the commit message
- the repository must exist locally
- commit links depend on `remote.origin.url`

## Packagist Publishing

1. Upload this package to a separate public Git repository.
2. Create a semantic tag, for example `v1.0.0`.
3. Register the repository on Packagist.
4. Run `composer require JeanCarloMS/filament-release-notes` in the consuming project.

## License

MIT
