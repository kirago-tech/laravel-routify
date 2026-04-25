<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Paths to scan
    |--------------------------------------------------------------------------
    |
    | Absolute root directories Routify will recursively scan for route
    | files. Missing paths raise a RoutifyException at scan time so silent
    | "scanned-and-found-nothing" misconfigurations cannot ship.
    |
    */
    'paths' => [
        app_path('Modules'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-discovery on boot
    |--------------------------------------------------------------------------
    |
    | When true, the package registers every enabled stack as soon as the
    | service provider boots. When false, the host application drives
    | discovery explicitly via Routify::discoverApi() / discoverWeb() / etc.
    |
    */
    'auto_discover_on_boot' => true,

    /*
    |--------------------------------------------------------------------------
    | Stack definitions
    |--------------------------------------------------------------------------
    |
    | A stack is one family of route files (web, api, console, channels)
    | or any custom family you declare. Each stack is described by:
    |
    |   - enabled    : bool   — enables the stack for Routify::discover()
    |   - pattern    : string — glob matched against discovered files
    |                            (relative to each configured path, recursive)
    |   - middleware : array  — middleware group(s) applied
    |   - prefix     : ?string — URL prefix (e.g. "api/v1")
    |   - name       : ?string — route name prefix (e.g. "api.")
    |   - domain     : ?string — domain restriction
    |
    | You can declare your own stacks (e.g. an "admin" stack with the
    | ['web', 'auth', 'admin'] middleware) and load it via
    | Routify::for('admin')->load().
    |
    */
    'stacks' => [

        'web' => [
            'enabled' => true,
            'pattern' => 'web*.php',
            'middleware' => ['web'],
            'prefix' => null,
            'name' => null,
            'domain' => null,
        ],

        'api' => [
            'enabled' => true,
            'pattern' => 'api*.php',
            'middleware' => ['api'],
            'prefix' => 'api',
            'name' => 'api.',
            'domain' => null,
        ],

        'console' => [
            'enabled' => true,
            'pattern' => 'console*.php',
            'middleware' => [],
            'prefix' => null,
            'name' => null,
            'domain' => null,
        ],

        'channels' => [
            'enabled' => true,
            'pattern' => 'channels*.php',
            'middleware' => [],
            'prefix' => null,
            'name' => null,
            'domain' => null,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache of the discovery result
    |--------------------------------------------------------------------------
    |
    | Filesystem scans cost milliseconds per stack on every boot. Enable
    | this in production: the list of discovered files is then memoised
    | on the configured cache store. Use `php artisan routify:cache` to
    | warm it and `php artisan routify:clear` to invalidate after a deploy.
    |
    */
    'cache' => [
        'enabled' => env('ROUTIFY_CACHE', false),
        'key' => 'routify:files',
        'store' => null,
    ],
];
