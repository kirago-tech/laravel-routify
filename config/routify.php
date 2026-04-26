<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Paths to scan
    |--------------------------------------------------------------------------
    |
    | Absolute root directories Routify will recursively scan for route
    | files. The default is empty so a fresh install never crashes — even
    | with auto_discover_on_boot enabled, an empty paths array is a no-op.
    |
    | Add the directories your app actually uses. Common examples:
    |
    |   'paths' => [
    |       app_path('Modules'),
    |       app_path('Features'),
    |       base_path('packages'),
    |   ],
    |
    | A path that is configured but does not exist will throw a
    | RoutifyException at scan time — silent "scanned-and-found-nothing"
    | misconfigurations cannot ship.
    |
    */
    'paths' => [],

    /*
    |--------------------------------------------------------------------------
    | Auto-discovery on boot
    |--------------------------------------------------------------------------
    |
    | When true, the package registers every enabled stack as soon as the
    | service provider boots. When false, the host application drives
    | discovery explicitly via Routify::discoverApi() / discoverWeb() / etc.
    |
    | In production, pair this with cache.enabled = true (see below) to
    | avoid scanning the filesystem on every request.
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
    |   - middleware : array  — middleware group(s) applied (HTTP only)
    |   - prefix     : ?string — URL prefix (HTTP only, e.g. "api/v1")
    |   - name       : ?string — route name prefix (HTTP only, e.g. "api.")
    |   - domain     : ?string — domain restriction (HTTP only)
    |   - context    : string — "http" (default), "cli" or "broadcast".
    |                            CLI and broadcast files are require_once'd
    |                            into Artisan / Broadcast contexts directly,
    |                            without going through Route::group().
    |
    | The `console` and `channels` stacks default to context "cli" and
    | "broadcast" respectively — you do not need to set context for them.
    |
    | Declare your own stacks freely (e.g. an "admin" stack with the
    | ['web', 'auth', 'admin'] middleware). Routify::for('admin')->load()
    | loads them on demand.
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
    | `php artisan routify:optimize` does both in one call — perfect for
    | CI/CD pipelines.
    |
    */
    'cache' => [
        'enabled' => env('ROUTIFY_CACHE', true),
        'key' => 'routify:files',
        'store' => null,
    ],
];
