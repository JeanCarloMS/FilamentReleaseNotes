# jeancarloms Filament Release Notes

Plugin de Filament para mostrar un timeline de commits del repositorio Git actual como una pagina tipo "Release Notes".

Incluye:

- paginacion real sobre `git log`
- busqueda por palabras en commits
- links directos al commit en GitHub
- integracion con `Filament Shield`
- configuracion por panel

## Requisitos

- PHP 8.2+
- Laravel 12+
- Filament 3.3+
- `bezhansalleh/filament-shield`
- acceso local al repositorio Git del proyecto

## Instalacion

```bash
composer require jeancarloms/filament-release-notes
```

## Publicar configuracion

```bash
php artisan vendor:publish --tag=filament-release-notes-config
```

## Registrar el plugin en Filament

```php
use jeancarloms\FilamentReleaseNotes\FilamentReleaseNotesPlugin;

FilamentReleaseNotesPlugin::make()
    ->navigationGroup('System')
    ->navigationSort(11)
    ->navigationIcon('heroicon-o-sparkles')
    ->slug('release-notes')
    ->title('Release Notes')
    ->heading('Release Notes')
    ->subheading('Consulta el historial visual de cambios del repositorio actual.')
    ->defaultPerPage(100)
    ->perPageOptions([25, 50, 100, 150, 200])
    ->repositoryPath(base_path())
```

## Shield

La pagina usa `HasPageShield`, por lo tanto el permiso requerido es:

```text
page_ReleaseNotes
```

El plugin intenta crear ese permiso automaticamente si la tabla `permissions` existe.

## Configuracion

Archivo: `config/filament-release-notes.php`

Opciones disponibles:

- `git_binary`
- `repository_path`
- `branch`
- `default_per_page`
- `per_page_options`
- `cache_store`
- `cache_ttl`
- `date_format`

## Limitaciones

- la busqueda se realiza sobre el mensaje del commit
- el repositorio debe existir localmente
- los links a commits dependen de `remote.origin.url`

## Publicacion en Packagist

1. Sube este paquete a un repositorio Git publico independiente.
2. Crea un tag semantico, por ejemplo `v1.0.0`.
3. Registra el repositorio en Packagist.
4. Ejecuta `composer require jeancarloms/filament-release-notes` en el proyecto consumidor.

## Licencia

MIT
