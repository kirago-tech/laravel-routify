# Laravel Routify

[![Packagist Version](https://img.shields.io/packagist/v/kirago/laravel-routify.svg?style=flat-square)](https://packagist.org/packages/kirago/laravel-routify)
[![Tests](https://img.shields.io/github/actions/workflow/status/kirago-tech/laravel-routify/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/kirago-tech/laravel-routify/actions/workflows/tests.yml)
[![Code Style](https://img.shields.io/github/actions/workflow/status/kirago-tech/laravel-routify/pint.yml?branch=main&label=pint&style=flat-square)](https://github.com/kirago-tech/laravel-routify/actions/workflows/pint.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/kirago/laravel-routify.svg?style=flat-square)](https://packagist.org/packages/kirago/laravel-routify)
[![License](https://img.shields.io/packagist/l/kirago/laravel-routify.svg?style=flat-square)](LICENSE.md)

**Auto-discover and register Laravel route files spread across multiple folders.**
Configurable glob patterns, middleware groups, stacks (web / api / console / channels — or your own), opt-in filesystem cache, and a fluent builder for the cases the config can't express.

---

## Why this package

Out of the box, Laravel only auto-loads `routes/web.php`, `routes/api.php`, `routes/console.php` and `routes/channels.php`. Any architecture that splits routes by module, feature, bounded context or plugin (`app/Modules/*/Routes/api.php`, `packages/*/routes/web.php`, …) ends up with a `RouteServiceProvider` full of manual `Route::group($file)` calls — tedious and easy to drift.

`routify` replaces that boilerplate with one line: declare *where* to look and *what to look for*, and the package walks the tree, applies your middleware/prefix/name/domain group, and registers everything.

---

## Installation

```bash
composer require kirago/laravel-routify
```

The package auto-registers via Laravel's package discovery. Publish the config when you want to customise it:

```bash
php artisan vendor:publish --tag=routify-config
```

Requires PHP 8.2+ and Laravel 11 or 12.

---

## Quick start

By default, `auto_discover_on_boot` is `true` — once the package is installed and `paths` points to your modules root, every enabled stack is loaded automatically. Drop your route files anywhere under that root:

```
app/Modules/
├── Billing/Routes/api.php       → Route::get('/invoices', …)
├── Billing/Routes/web.php
├── Catalog/Routes/api.php
└── Catalog/Routes/api-v2.php
```

…and they all become `api/invoices`, `api/v2/*` etc. with the `api` middleware group applied.

If you prefer explicit control, set `auto_discover_on_boot => false` and call from your `AppServiceProvider::boot()` (or `bootstrap/app.php`):

```php
use Kirago\Routify\Facades\Routify;

Routify::discover();              // every enabled stack
Routify::discoverApi();           // only the api stack
Routify::discoverApi('api/v1');   // override the prefix
Routify::discoverWeb();
Routify::discoverConsole();
Routify::discoverChannels();
```

---

## Configuration

`config/routify.php`:

```php
return [

    // Absolute root directories scanned recursively. Missing paths
    // throw at scan time — no silent "found nothing" misconfigs.
    'paths' => [
        app_path('Modules'),
    ],

    // When true, every enabled stack is loaded as soon as the package
    // boots. When false, you drive discovery explicitly via the facade.
    'auto_discover_on_boot' => true,

    'stacks' => [
        'web' => [
            'enabled'    => true,
            'pattern'    => 'web*.php',
            'middleware' => ['web'],
            'prefix'     => null,
            'name'       => null,
            'domain'     => null,
        ],
        'api' => [
            'enabled'    => true,
            'pattern'    => 'api*.php',
            'middleware' => ['api'],
            'prefix'     => 'api',
            'name'       => 'api.',
            'domain'     => null,
        ],
        'console'  => [ 'enabled' => true, 'pattern' => 'console*.php',  'middleware' => [], 'prefix' => null, 'name' => null, 'domain' => null ],
        'channels' => [ 'enabled' => true, 'pattern' => 'channels*.php', 'middleware' => [], 'prefix' => null, 'name' => null, 'domain' => null ],
    ],

    'cache' => [
        'enabled' => env('ROUTIFY_CACHE', false),
        'key'     => 'routify:files',
        'store'   => null, // null = the default cache store
    ],
];
```

### Custom stacks

Declare anything you want — `routify` does not assume `web` and `api` are the only valid stacks:

```php
'stacks' => [
    // …
    'admin' => [
        'enabled'    => true,
        'pattern'    => 'admin*.php',
        'middleware' => ['web', 'auth', 'admin'],
        'prefix'     => 'admin',
        'name'       => 'admin.',
        'domain'     => null,
    ],
],
```

Then load it explicitly:

```php
Routify::for('admin')->load();
```

### Multiple paths

```php
'paths' => [
    app_path('Modules'),
    app_path('Features'),
    base_path('packages'),
],
```

Each path is scanned independently. If two paths overlap, the same file is registered exactly once.

---

## Fluent builder

For the cases the config can't express:

```php
Routify::for('api')
    ->in(app_path('Modules'))           // override paths (one or many)
    ->in(base_path('packages/billing'))
    ->withPrefix('api/v2')              // override URL prefix
    ->withMiddleware(['api', 'throttle:60,1'])
    ->withName('api.v2.')               // route-name prefix
    ->withDomain('{tenant}.example.com')
    ->matching('api-v2*.php')           // override the glob pattern
    ->load();
```

Every method returns the builder, so order is irrelevant. `load()` is the terminal call.

---

## Artisan commands

```bash
php artisan routify:list                # tabular view of every discovered file
php artisan routify:list --stack=api    # restrict to one stack
php artisan routify:cache               # warm the discovery cache
php artisan routify:clear               # invalidate the discovery cache
```

`routify:cache` and `routify:clear` only do useful work when `routify.cache.enabled` is `true` (typically `ROUTIFY_CACHE=true` in production). The cache is on the filesystem scan — Laravel's own `route:cache` is orthogonal and complementary.

---

## Testing

```bash
composer test            # runs the Pest suite via Orchestra Testbench
composer test:coverage   # with coverage (requires Xdebug or PCOV)
composer format:test     # Laravel Pint --test
composer analyse         # PHPStan
```

---

## Architecture

The why-and-how of every architectural choice is documented as
Architecture Decision Records under [`docs/adr/`](docs/adr/) (in French).
Start with [ADR-0001 — Layered architecture](docs/adr/0001-architecture-en-couches.md)
for the big picture.

---

## Roadmap

Post-1.0 ideas, not committed:

- per-stack `Route::scopeBindings()`
- OpenAPI doc generation from discovered routes
- `pestphp/pest-plugin-laravel` assertions (`expect()->toHaveDiscoveredRoute('api.users.index')`)
- multi-tenant dynamic paths
- bridging the discovery cache into Laravel's native `route:cache` mechanism

---

## Contributing

Issues and pull requests on [GitHub](https://github.com/kirago-tech/laravel-routify) are welcome. Please run `composer test` and `composer format:test` before submitting.

---

## License

MIT — see [LICENSE.md](LICENSE.md). Copyright © 2026 [Simo Joel](mailto:joel.simo@kirago.tech).
