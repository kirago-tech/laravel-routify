<?php

declare(strict_types=1);

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Kirago\Routify\Contracts\RouteDiscoverer;
use Kirago\Routify\Discovery\CachedRouteDiscoverer;

function spyDiscoverer(array $result = []): RouteDiscoverer
{
    return new class($result) implements RouteDiscoverer
    {
        public int $calls = 0;

        public int $folderCalls = 0;

        /** @param  list<string>  $result */
        public function __construct(public array $result) {}

        public function discover(string $basePath, string $pattern): array
        {
            $this->calls++;

            return $this->result;
        }

        public function discoverInFolder(string $basePath, string $folderName): array
        {
            $this->folderCalls++;

            return $this->result;
        }
    };
}

function arrayCache(): Repository
{
    return new Repository(new ArrayStore);
}

it('forwards to the inner discoverer on cache miss and returns its result', function (): void {
    $inner = spyDiscoverer(['/tmp/api.php']);
    $cached = new CachedRouteDiscoverer($inner, arrayCache(), 'routify:files');

    $result = $cached->discover('/tmp', 'api*.php');

    expect($result)->toBe(['/tmp/api.php']);
    expect($inner->calls)->toBe(1);
});

it('does not call the inner discoverer on a cache hit (same basePath and pattern)', function (): void {
    $inner = spyDiscoverer(['/tmp/api.php']);
    $cached = new CachedRouteDiscoverer($inner, arrayCache(), 'routify:files');

    $first = $cached->discover('/tmp', 'api*.php');
    $second = $cached->discover('/tmp', 'api*.php');

    expect($first)->toBe($second);
    expect($inner->calls)->toBe(1);
});

// ---------------------------------------------------------------------------
// discoverInFolder (1.1 — folder-based discovery)
// ---------------------------------------------------------------------------

it('forwards discoverInFolder to the inner discoverer on cache miss and returns its result', function (): void {
    $inner = spyDiscoverer(['/tmp/api/billing.php']);
    $cached = new CachedRouteDiscoverer($inner, arrayCache(), 'routify:files');

    $result = $cached->discoverInFolder('/tmp', 'api');

    expect($result)->toBe(['/tmp/api/billing.php']);
    expect($inner->folderCalls)->toBe(1);
});

it('does not call the inner discoverer on a cache hit for discoverInFolder (same basePath and folderName)', function (): void {
    $inner = spyDiscoverer(['/tmp/api/billing.php']);
    $cached = new CachedRouteDiscoverer($inner, arrayCache(), 'routify:files');

    $first = $cached->discoverInFolder('/tmp', 'api');
    $second = $cached->discoverInFolder('/tmp', 'api');

    expect($first)->toBe($second);
    expect($inner->folderCalls)->toBe(1);
});

it('uses distinct cache keys for discover() and discoverInFolder() even when arguments share the same string', function (): void {
    // basePath '/tmp' + 'api' must not collide between discover(.., 'api')
    // (pattern call) and discoverInFolder(.., 'api') (folder call).
    $inner = new class implements RouteDiscoverer
    {
        public function discover(string $basePath, string $pattern): array
        {
            return ['pattern:'.$basePath.':'.$pattern];
        }

        public function discoverInFolder(string $basePath, string $folderName): array
        {
            return ['folder:'.$basePath.':'.$folderName];
        }
    };
    $cached = new CachedRouteDiscoverer($inner, arrayCache(), 'routify:files');

    expect($cached->discover('/tmp', 'api'))->toBe(['pattern:/tmp:api']);
    expect($cached->discoverInFolder('/tmp', 'api'))->toBe(['folder:/tmp:api']);
});

it('uses distinct cache keys for distinct (basePath, pattern) pairs', function (): void {
    $inner = new class implements RouteDiscoverer
    {
        public int $calls = 0;

        public function discover(string $basePath, string $pattern): array
        {
            $this->calls++;

            return [$basePath.':'.$pattern];
        }

        public function discoverInFolder(string $basePath, string $folderName): array
        {
            return [];
        }
    };
    $cached = new CachedRouteDiscoverer($inner, arrayCache(), 'routify:files');

    $apiInModules = $cached->discover('/app/Modules', 'api*.php');
    $webInModules = $cached->discover('/app/Modules', 'web*.php');
    $apiInFeatures = $cached->discover('/app/Features', 'api*.php');

    expect($apiInModules)->toBe(['/app/Modules:api*.php']);
    expect($webInModules)->toBe(['/app/Modules:web*.php']);
    expect($apiInFeatures)->toBe(['/app/Features:api*.php']);
    expect($inner->calls)->toBe(3);
});
