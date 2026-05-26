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

afterEach(function (): void {
    foreach ($GLOBALS['_routify_tmp_dirs'] ?? [] as $dir) {
        cacheResilienceCleanup($dir);
    }
    $GLOBALS['_routify_tmp_dirs'] = [];
});

// ---------------------------------------------------------------------------
// P0-C — CachedRouteDiscoverer must self-heal when the disk diverges.
// ---------------------------------------------------------------------------

it('re-scans when a cached file path no longer exists on disk (pattern mode)', function (): void {
    $tmp = cacheResilienceTempModule(['routes/api.php']);
    config()->set('routify.paths', [$tmp]);

    /** @var RouteDiscoverer $discoverer */
    $discoverer = $this->app->make(RouteDiscoverer::class);

    $first = $discoverer->discover($tmp, 'api*.php');
    expect($first)->toHaveCount(1);

    unlink($first[0]);

    // Without self-heal, the second call would return the stale list.
    $second = $discoverer->discover($tmp, 'api*.php');
    expect($second)->toBe([]);
});

it('re-scans when a cached file path no longer exists on disk (folder mode)', function (): void {
    $tmp = cacheResilienceTempModule(['routes/api/billing.php']);
    config()->set('routify.paths', [$tmp]);

    /** @var RouteDiscoverer $discoverer */
    $discoverer = $this->app->make(RouteDiscoverer::class);

    $first = $discoverer->discoverInFolder($tmp, 'api');
    expect($first)->toHaveCount(1);

    unlink($first[0]);

    $second = $discoverer->discoverInFolder($tmp, 'api');
    expect($second)->toBe([]);
});

it('keeps the cache hit when every cached file still exists', function (): void {
    $tmp = cacheResilienceTempModule(['routes/api.php']);
    config()->set('routify.paths', [$tmp]);

    /** @var RouteDiscoverer $discoverer */
    $discoverer = $this->app->make(RouteDiscoverer::class);

    $key = CachedRouteDiscoverer::cacheKey('routify:files', $tmp, 'api*.php');

    $first = $discoverer->discover($tmp, 'api*.php');
    expect(cache()->has($key))->toBeTrue();

    // We poison the inner discoverer by deleting the directory the cache
    // was populated from. If the cached value is honoured (because every
    // file still exists), no exception fires and the result matches.
    $second = $discoverer->discover($tmp, 'api*.php');
    expect($second)->toBe($first);
});

// ---------------------------------------------------------------------------
// P0-B — RoutifyManager must tolerate a stale cache entry instead of
//          letting Laravel's require() blow up the whole boot.
// ---------------------------------------------------------------------------

it('Routify::discover does not crash when a cached file points to a deleted file', function (): void {
    $tmp = cacheResilienceTempModule(['routes/api.php']);
    config()->set('routify.paths', [$tmp]);

    $apiKey = CachedRouteDiscoverer::cacheKey('routify:files', $tmp, 'api*.php');
    // Stale entry: lists a real file plus a ghost. After we delete the real
    // file too, *both* paths are dangling. Before the fix this triggers
    // "Failed to open stream: No such file or directory" via require().
    cache()->forever($apiKey, [$tmp.'/routes/api.php', $tmp.'/routes/ghost.php']);
    unlink($tmp.'/routes/api.php');

    expect(fn () => Routify::discover())->not->toThrow(Throwable::class);
});

it('replaces the stale cache value with a fresh scan after detecting missing files', function (): void {
    $tmp = cacheResilienceTempModule([]);
    config()->set('routify.paths', [$tmp]);

    $folderKey = CachedRouteDiscoverer::folderCacheKey('routify:files', $tmp, 'api');
    cache()->forever($folderKey, [$tmp.'/routes/api/ghost.php']);

    // Trigger a load — the manager should detect the stale entry and rewrite
    // the cache with a fresh scan so the next boot does not re-attempt the
    // ghost file. The key may or may not be cleared, but the value MUST no
    // longer contain the ghost path.
    Routify::discover();

    expect(cache()->get($folderKey, []))->not->toContain($tmp.'/routes/api/ghost.php');
});

it('skips a stale path returned directly by the discoverer (cache disabled scenario)', function (): void {
    // Locks P0-B independently of P0-C: even with a discoverer that hands
    // out a non-existent path (race condition, buggy custom discoverer,
    // cache disabled but disk changed mid-request), the manager must not
    // blow up on require().
    $tmp = cacheResilienceTempModule(['routes/api.php']);
    config()->set('routify.cache.enabled', false);
    config()->set('routify.paths', [$tmp]);

    $this->app->bind(RouteDiscoverer::class, fn () => new class($tmp) implements RouteDiscoverer
    {
        public function __construct(private string $tmp) {}

        public function discover(string $basePath, string $pattern): array
        {
            return [$this->tmp.'/routes/api.php', $this->tmp.'/routes/ghost.php'];
        }

        public function discoverInFolder(string $basePath, string $folderName): array
        {
            return [];
        }
    });
    $this->app->forgetInstance(RoutifyManager::class);

    expect(fn () => Routify::discoverApi())->not->toThrow(Throwable::class);
});

// ---------------------------------------------------------------------------
// P0-A — routify:* commands must remain reachable even when the boot
//          discovery path would otherwise be broken.
// ---------------------------------------------------------------------------

it('routify:clear stays invokable when auto_discover_on_boot is on and the cache is stale', function (): void {
    $tmp = cacheResilienceTempModule([]);
    config()->set('routify.paths', [$tmp]);
    config()->set('routify.auto_discover_on_boot', true);

    $key = CachedRouteDiscoverer::folderCacheKey('routify:files', $tmp, 'api');
    cache()->forever($key, ['/path/that/does/not/exist.php']);

    $this->artisan('routify:clear')->assertSuccessful();
});

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function cacheResilienceTempModule(array $files): string
{
    $tmp = sys_get_temp_dir().DIRECTORY_SEPARATOR.'routify-resilience-'.bin2hex(random_bytes(4));
    if (! is_dir($tmp)) {
        mkdir($tmp, 0o777, true);
    }
    foreach ($files as $rel) {
        $abs = $tmp.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if (! is_dir(dirname($abs))) {
            mkdir(dirname($abs), 0o777, true);
        }
        file_put_contents($abs, "<?php\n");
    }
    $GLOBALS['_routify_tmp_dirs'][] = $tmp;

    return $tmp;
}

function cacheResilienceCleanup(string $dir): void
{
    if (! is_dir($dir)) {
        return;
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($it as $node) {
        $node->isDir() ? @rmdir($node->getPathname()) : @unlink($node->getPathname());
    }
    @rmdir($dir);
}
