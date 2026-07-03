<?php

return [
    // Catalog files to sync. Format is detected by extension:
    // .json for flat JSON catalogs, .php for flat PHP array catalogs.
    'lang_files' => [
        // base_path('lang/ar.json'), // laravel 12
        // base_path('lang/ar.php'), // laravel 12, PHP catalog
        // resource_path('lang/ar.json'), // laravel < 12
    ],
    'scan_paths' => [
        base_path('app'),
        base_path('resources'),
        base_path('config'),
    ],
    'remove_unused_keys' => false,
];
