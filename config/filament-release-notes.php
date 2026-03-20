<?php

declare(strict_types=1);

return [
    'git_binary' => env('FILAMENT_RELEASE_NOTES_GIT_BINARY', 'git'),
    'repository_path' => base_path(),
    'branch' => env('FILAMENT_RELEASE_NOTES_BRANCH'),
    'default_per_page' => (int) env('FILAMENT_RELEASE_NOTES_DEFAULT_PER_PAGE', 100),
    'per_page_options' => [25, 50, 100, 150, 200],
    'cache_store' => env('FILAMENT_RELEASE_NOTES_CACHE_STORE'),
    'cache_ttl' => (int) env('FILAMENT_RELEASE_NOTES_CACHE_TTL', 300),
    'date_format' => 'M d, Y \a\t H:i',
];
