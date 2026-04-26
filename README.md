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

### The pain: `routes/api.php` rots

In a fresh Laravel app, every API route lives in `routes/api.php`. That file
keeps growing. By the time the app has billing, auth, inventory, users,
notifications and a few admin endpoints, it looks like this:

```php
// routes/api.php — 400+ lines, nobody wants to touch it
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/users',         [UserController::class, 'index']);
    Route::post('/users',        [UserController::class, 'store']);
    Route::get('/users/{user}',  [UserController::class, 'show']);
    // … 30 more user routes …

    Route::get('/invoices',      [InvoiceController::class, 'index']);
    Route::post('/invoices',     [InvoiceController::class, 'store']);
    // … 25 more billing routes …

    Route::get('/products',      [ProductController::class, 'index']);
    // … 20 more inventory routes …
});
```

Pull-request reviews on this file are painful. Merge conflicts are constant.
Finding *"where is the route for X"* requires `grep`. Multiply by `web.php`,
`console.php`, `channels.php` and any custom stack (`admin`, `internal`, …)
and the boilerplate compounds.

### The disciplined workaround that's still painful

The seasoned move is to split per feature and `require` each piece manually
from a central group:

```php
// routes/api.php
Route::middleware('api')->prefix('api')->group(function () {
    require __DIR__.'/api/billing.php';
    require __DIR__.'/api/users.php';
    require __DIR__.'/api/inventory.php';
    require __DIR__.'/api/auth.php';
    require __DIR__.'/api/notifications.php';
    require __DIR__.'/api/admin.php';
    // adding a new module? don't forget to add it here too…
});
```

Or worse — the same boilerplate inflated inside a `RouteServiceProvider`
with five `Route::group(…)->group(__DIR__.'/…')` chains, one per feature
folder.

It's better than the giant file, but every new module forces a touch on this
central index. **Forget the line and the routes silently disappear in
production.** That last part is the killer: there's no compiler error, no
test failure unless you remembered to write one — just a 404 on staging at
9 PM the day before launch.

### What Routify does

Point Routify at a folder, drop your route files anywhere underneath, stop
maintaining the index:

```php
// bootstrap/app.php (or any service provider)
use Kirago\Routify\Facades\Routify;

Routify::discover();
```

```
app/Modules/
├── Billing/Routes/api.php          → loaded as api/* with the api stack
├── Billing/Routes/web.php          → loaded as web/* with the web stack
├── Catalog/Routes/api.php
├── Catalog/Routes/api-v2.php       → versioning is just a filename
└── Users/Routes/api.php
```

- Add a module → its routes are picked up. No central file to edit.
- Delete a module → its routes disappear with it. No dangling `require`.
- Version your API by creating `api-v2.php` next to `api.php`. The default
  glob pattern `api*.php` matches both.
- Need a custom stack (`admin`, `internal`, `webhooks`, `tenant-api`)? Declare
  it in `config/routify.php` and Routify treats it like a first-class citizen.

The "register every file by hand" tax is gone.

---

## Installation

```bash
composer require kirago/laravel-routify
```

The package auto-registers via Laravel's package discovery. Publish the config to customise it:

```bash
php artisan vendor:publish --tag=routify-config
```

Requires PHP 8.2+ and Laravel 11 or 12.

> **Zero-crash install** — the shipped config has `paths => []`. The package
> is wired but does nothing until you point it at the directories your app
> uses. No surprise exceptions on first boot.

---

## Quick start

1. Add your route directories to `config/routify.php`:

```php
'paths' => [
    app_path('Modules'),
    // app_path('Features'),
    // base_path('packages'),
],
```

2. Drop route files anywhere under those roots:

```
app/Modules/
├── Billing/Routes/api.php       → Route::get('/invoices', …)
├── Billing/Routes/web.php
├── Catalog/Routes/api.php
└── Catalog/Routes/api-v2.php
```

…and they all become `api/invoices`, `api/v2/*` etc. with the `api`
middleware group applied. With `auto_discover_on_boot = true` (the default),
that's it — no extra wiring.

For explicit control, set `auto_discover_on_boot => false` and call from
your `AppServiceProvider::boot()` (recommended location — facades are
guaranteed to be ready there):

```php
// app/Providers/AppServiceProvider.php
use Kirago\Routify\Facades\Routify;

public function boot(): void
{
    Routify::discover();              // every enabled stack
    Routify::discoverApi();           // only the api stack
    Routify::discoverApi('api/v1');   // override the prefix
    Routify::discoverWeb();
    Routify::discoverConsole();
    Routify::discoverChannels();
}
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
php artisan routify:optimize            # clear + cache in one call
```

`routify:cache`, `routify:clear` and `routify:optimize` only do useful work
when `routify.cache.enabled` is `true` (typically `ROUTIFY_CACHE=true` in
production). The cache is on the filesystem scan — Laravel's own
`route:cache` is orthogonal and complementary.

---

## Production deployment

In production, scanning the filesystem on every boot is wasteful. Enable the
cache and warm it at deploy time:

```dotenv
# .env (production)
ROUTIFY_CACHE=true
```

Add the warm-up to the deployment script alongside the standard Laravel
optimisations:

```bash
composer install --no-dev --optimize-autoloader
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan routify:optimize     # clears stale entries, re-warms the discovery cache
```

### CI/CD via Composer scripts

To trigger Routify's cache refresh automatically on `composer install` /
`composer update`, add this to **your application's** `composer.json`
(not the package — Composer scripts live in the consuming app):

```json
{
    "scripts": {
        "post-install-cmd": [
            "@php artisan routify:optimize --ansi"
        ],
        "post-update-cmd": [
            "@php artisan routify:optimize --ansi"
        ]
    }
}
```

The `routify:optimize` call is a no-op when the cache is disabled, so it is
safe to keep enabled across all environments.

---

## Security considerations

Routify `require`s the route files it discovers. The same trust model as
Laravel's own `routes/` directory applies — with one important shift: the
list of paths now comes from your config, so the surface for misconfig is
larger. Keep these in mind:

- **Never put a writable directory in `routify.paths`.** Anything an
  attacker can write to (uploads dir, runtime cache, tmp) becomes a code
  execution vector at boot if Routify scans it.
- **Symlinks are followed by default** (Symfony Finder behaviour). On
  multi-tenant or shared-hosting setups where tenant-controlled directories
  live under your scan paths, audit the symlinks.
- **Cache poisoning = boot RCE.** If `routify.cache.enabled = true` and the
  cache backend is compromised, an attacker can inject arbitrary file paths
  into the cache. This is the standard Laravel cache trust model — protect
  your Redis/Memcached/database cache the same way you protect
  `bootstrap/cache/`.

In short: treat `routify.paths` like you treat the `routes/` directory.
Don't stage anything writable by the runtime web user under it.

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
