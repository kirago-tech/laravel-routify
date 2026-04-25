<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Kirago\Routify\Facades\Routify;

it('loads only the routes from the path passed to in()', function (): void {
    Routify::for('api')
        ->in(__DIR__.'/../fixtures/modules/ModuleA')
        ->load();

    expect(Route::has('api.users.index'))->toBeTrue()
        ->and(Route::has('api.orders.store'))->toBeFalse();
});

it('lets the caller override prefix, name and middleware on the fly', function (): void {
    Routify::for('api')
        ->in(__DIR__.'/../fixtures/modules/ModuleA')
        ->withPrefix('internal/v3')
        ->withName('internal.')
        ->withMiddleware(['api', 'auth:sanctum'])
        ->load();

    $route = Route::getRoutes()->getByName('internal.users.index');
    expect($route)->not->toBeNull()
        ->and($route->uri())->toBe('internal/v3/users')
        ->and($route->middleware())->toContain('api')
        ->and($route->middleware())->toContain('auth:sanctum');
});

it('matching() lets the caller override the glob pattern', function (): void {
    Routify::for('api')
        ->in(__DIR__.'/../fixtures/modules/ModuleB')
        ->matching('api-v2*.php')
        ->load();

    expect(Route::has('api.orders.v2.index'))->toBeTrue()
        ->and(Route::has('api.orders.store'))->toBeFalse();
});
