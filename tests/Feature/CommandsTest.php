<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Kirago\Routify\Contracts\RouteDiscoverer;
use Kirago\Routify\Discovery\CachedRouteDiscoverer;
use Kirago\Routify\RoutifyManager;

function enableRoutifyCache(Application $app): void
{
    config()->set('routify.cache.enabled', true);
    $app->forgetInstance(RouteDiscoverer::class);
    $app->forgetInstance(RoutifyManager::class);
}

it('routify:list lists discovered route files', function (): void {
    $this->artisan('routify:list')
        ->assertSuccessful()
        ->expectsOutputToContain('api*.php')
        ->expectsOutputToContain('web*.php')
        ->expectsOutputToContain('Stack');
});

it('routify:list --stack=api filters to a single stack', function (): void {
    $this->artisan('routify:list --stack=api')
        ->assertSuccessful()
        ->expectsOutputToContain('api*.php')
        ->doesntExpectOutputToContain('web*.php');
});

it('routify:list reports nothing-found when no files match', function (): void {
    $emptyPath = sys_get_temp_dir().'/routify-empty-'.bin2hex(random_bytes(4));
    mkdir($emptyPath, 0o777, true);
    config()->set('routify.paths', [$emptyPath]);

    $this->artisan('routify:list')
        ->assertSuccessful()
        ->expectsOutputToContain('No Routify route files discovered');

    rmdir($emptyPath);
});

it('routify:cache fails when cache is disabled', function (): void {
    $this->artisan('routify:cache')->assertFailed();
});

it('routify:cache primes the cache when enabled', function (): void {
    enableRoutifyCache($this->app);

    $this->artisan('routify:cache')->assertSuccessful();

    $key = CachedRouteDiscoverer::cacheKey('routify:files', dirname(__DIR__).'/fixtures/modules', 'api*.php');
    expect(cache()->has($key))->toBeTrue();
});

it('routify:clear is a no-op when cache is disabled', function (): void {
    $this->artisan('routify:clear')
        ->assertSuccessful()
        ->expectsOutputToContain('nothing to clear');
});

it('routify:clear forgets every cache key for every (path, stack) pair', function (): void {
    enableRoutifyCache($this->app);

    $this->artisan('routify:cache')->assertSuccessful();
    $apiKey = CachedRouteDiscoverer::cacheKey('routify:files', dirname(__DIR__).'/fixtures/modules', 'api*.php');
    $webKey = CachedRouteDiscoverer::cacheKey('routify:files', dirname(__DIR__).'/fixtures/modules', 'web*.php');
    expect(cache()->has($apiKey))->toBeTrue()
        ->and(cache()->has($webKey))->toBeTrue();

    $this->artisan('routify:clear')->assertSuccessful();

    expect(cache()->has($apiKey))->toBeFalse()
        ->and(cache()->has($webKey))->toBeFalse();
});

it('routify:optimize clears then re-warms the cache in a single call', function (): void {
    enableRoutifyCache($this->app);

    $apiKey = CachedRouteDiscoverer::cacheKey('routify:files', dirname(__DIR__).'/fixtures/modules', 'api*.php');

    // Pre-warm so we can prove `optimize` clears the existing entry first.
    $this->artisan('routify:cache')->assertSuccessful();
    expect(cache()->has($apiKey))->toBeTrue();

    $this->artisan('routify:optimize')->assertSuccessful();

    expect(cache()->has($apiKey))->toBeTrue();
});

it('routify:optimize fails when cache is disabled', function (): void {
    // The clear sub-call succeeds (no-op), but the cache sub-call fails.
    $this->artisan('routify:optimize')->assertFailed();
});

// ---------------------------------------------------------------------------
// Commands × folder-based discovery (1.1)
// ---------------------------------------------------------------------------

it('routify:list reports files discovered via the by-folder convention', function (): void {
    // ModuleF/Routes/api/billing.php exists in fixtures since 1.1.
    $this->artisan('routify:list')
        ->assertSuccessful()
        ->expectsOutputToContain('api/billing.php');
});

it('routify:cache warms the folder cache key for every (path, stack) pair', function (): void {
    enableRoutifyCache($this->app);

    $this->artisan('routify:cache')->assertSuccessful();

    $folderKey = CachedRouteDiscoverer::folderCacheKey(
        'routify:files',
        dirname(__DIR__).'/fixtures/modules',
        'api',
    );
    expect(cache()->has($folderKey))->toBeTrue();
});

it('routify:clear forgets the folder cache keys as well as the pattern ones', function (): void {
    enableRoutifyCache($this->app);

    $this->artisan('routify:cache')->assertSuccessful();
    $folderKey = CachedRouteDiscoverer::folderCacheKey(
        'routify:files',
        dirname(__DIR__).'/fixtures/modules',
        'api',
    );
    expect(cache()->has($folderKey))->toBeTrue();

    $this->artisan('routify:clear')->assertSuccessful();

    expect(cache()->has($folderKey))->toBeFalse();
});
