<?php

declare(strict_types=1);

use Kirago\Routify\Contracts\RouteDiscoverer;
use Kirago\Routify\Discovery\CachedRouteDiscoverer;
use Kirago\Routify\Facades\Routify;
use Kirago\Routify\RoutifyManager;

beforeEach(function (): void {
    config()->set('routify.cache.enabled', true);
    $this->app->forgetInstance(RouteDiscoverer::class);
    $this->app->forgetInstance(RoutifyManager::class);
});

it('the cached discoverer is wired when routify.cache.enabled is true', function (): void {
    expect($this->app->make(RouteDiscoverer::class))->toBeInstanceOf(CachedRouteDiscoverer::class);
});

it('persists every (basePath, pattern) result on first discovery', function (): void {
    $apiKey = CachedRouteDiscoverer::cacheKey('routify:files', dirname(__DIR__).'/fixtures/modules', 'api*.php');
    expect(cache()->has($apiKey))->toBeFalse();

    Routify::discoverApi();

    expect(cache()->has($apiKey))->toBeTrue();
    expect(cache()->get($apiKey))->toBeArray()->toHaveCount(4);
});

it('skips the filesystem on a cache hit (manifest restored from cache)', function (): void {
    $apiKey = CachedRouteDiscoverer::cacheKey('routify:files', dirname(__DIR__).'/fixtures/modules', 'api*.php');

    // Since 1.1.1 the cached discoverer validates every cached path against
    // the disk; the seeded manifest must therefore reference real files
    // (any real fixture file does the job here).
    $seeded = [str_replace('\\', '/', dirname(__DIR__).'/fixtures/modules/ModuleA/Routes/api.php')];
    cache()->forever($apiKey, $seeded);

    /** @var CachedRouteDiscoverer $discoverer */
    $discoverer = $this->app->make(RouteDiscoverer::class);
    $found = $discoverer->discover(dirname(__DIR__).'/fixtures/modules', 'api*.php');

    expect($found)->toBe($seeded);
});

it('routify:clear forces the next discover() to re-hit the filesystem', function (): void {
    $apiKey = CachedRouteDiscoverer::cacheKey('routify:files', dirname(__DIR__).'/fixtures/modules', 'api*.php');

    Routify::discoverApi();
    expect(cache()->has($apiKey))->toBeTrue();

    $this->artisan('routify:clear')->assertSuccessful();
    expect(cache()->has($apiKey))->toBeFalse();
});
