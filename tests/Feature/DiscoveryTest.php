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
