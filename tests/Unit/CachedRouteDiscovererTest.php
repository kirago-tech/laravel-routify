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

        /** @param  list<string>  $result */
        public function __construct(public array $result) {}

        public function discover(string $basePath, string $pattern): array
        {
            $this->calls++;

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

it('uses distinct cache keys for distinct (basePath, pattern) pairs', function (): void {
    $inner = new class implements RouteDiscoverer
    {
        public int $calls = 0;

        public function discover(string $basePath, string $pattern): array
        {
            $this->calls++;

            return [$basePath.':'.$pattern];
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
