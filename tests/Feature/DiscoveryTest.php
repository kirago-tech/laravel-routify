<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Kirago\Routify\Facades\Routify;

function registeredNames(): array
{
    return collect(Route::getRoutes())
        ->map(fn ($r) => $r->getName())
        ->filter()
        ->values()
        ->all();
}

it('discoverApi registers every api*.php route file recursively', function (): void {
    Routify::discoverApi();

    expect(registeredNames())
        ->toContain('api.users.index')
        ->toContain('api.orders.store')
        ->toContain('api.orders.v2.index')
        ->toContain('api.extra.show');
});

it('discoverApi ignores files that do not match the api*.php pattern', function (): void {
    Routify::discoverApi();

    expect(registeredNames())
        ->not->toContain('dashboard')
        ->not->toContain('orphan.show');
});

it('applies the api/ URL prefix to every discovered api route', function (): void {
    Routify::discoverApi();

    $route = Route::getRoutes()->getByName('api.users.index');
    expect($route)->not->toBeNull()
        ->and($route->uri())->toBe('api/users');
});

it('overrides the api prefix when discoverApi is given an explicit one', function (): void {
    Routify::discoverApi('api/v1');

    $route = Route::getRoutes()->getByName('api.users.index');
    expect($route->uri())->toBe('api/v1/users');
});

it('applies the api middleware group to every discovered api route', function (): void {
    Routify::discoverApi();

    $route = Route::getRoutes()->getByName('api.users.index');
    expect($route->middleware())->toContain('api');
});

it('discoverWeb only loads web*.php files and skips api files', function (): void {
    Routify::discoverWeb();

    expect(registeredNames())
        ->toContain('dashboard')
        ->not->toContain('api.users.index');
});

it('discover() loads every enabled stack at once', function (): void {
    Routify::discover();

    expect(registeredNames())
        ->toContain('api.users.index')
        ->toContain('dashboard');
});

it('a stack with cli context does not register any HTTP route', function (): void {
    $before = Route::getRoutes()->count();

    Routify::discoverConsole();

    // The console fixture file was require_once'd into the Artisan context;
    // no HTTP routes should have been added to the router collection.
    expect(Route::getRoutes()->count())->toBe($before);
});

// ---------------------------------------------------------------------------
// Folder-based discovery (1.1)
// ---------------------------------------------------------------------------

it('discoverApi loads every .php file whose path includes an api/ folder, regardless of file name', function (): void {
    Routify::discoverApi();

    expect(registeredNames())
        ->toContain('api.billing.index')      // ModuleF/Routes/api/billing.php
        ->toContain('api.orders.v3.index');   // ModuleF/Routes/api/v3/orders.php (nested)
});

it('files under an api/ folder receive the stack middleware and url prefix', function (): void {
    Routify::discoverApi();

    $route = Route::getRoutes()->getByName('api.billing.index');
    expect($route)->not->toBeNull()
        ->and($route->uri())->toBe('api/billing')
        ->and($route->middleware())->toContain('api');
});

it('discoverWeb loads files under a web/ folder', function (): void {
    Routify::discoverWeb();

    $route = Route::getRoutes()->getByName('portal'); // ModuleG/Routes/web/portal.php
    expect($route)->not->toBeNull()
        ->and($route->middleware())->toContain('web');
});

it('discoverApi does NOT load files that sit under a web/ folder', function (): void {
    Routify::discoverApi();

    expect(registeredNames())->not->toContain('portal');
});

it('pattern-matched files and folder-matched files coexist without duplicate registration', function (): void {
    // ModuleA/Routes/api.php (pattern) and ModuleF/Routes/api/billing.php (folder)
    // both get loaded under the api stack. Each should appear exactly once.
    Routify::discoverApi();

    $names = registeredNames();
    expect(array_count_values($names)['api.users.index'] ?? 0)->toBe(1)
        ->and(array_count_values($names)['api.billing.index'] ?? 0)->toBe(1);
});
