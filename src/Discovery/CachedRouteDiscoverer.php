<?php

declare(strict_types=1);

namespace Kirago\Routify\Discovery;

use Illuminate\Contracts\Cache\Repository as Cache;
use Kirago\Routify\Contracts\RouteDiscoverer;

final class CachedRouteDiscoverer implements RouteDiscoverer
{
    public function __construct(
        private readonly RouteDiscoverer $inner,
        private readonly Cache $cache,
        private readonly string $keyPrefix,
    ) {}

    public function discover(string $basePath, string $pattern): array
    {
        return $this->cache->rememberForever(
            self::cacheKey($this->keyPrefix, $basePath, $pattern),
            fn (): array => $this->inner->discover($basePath, $pattern),
        );
    }

    public function discoverInFolder(string $basePath, string $folderName): array
    {
        return $this->cache->rememberForever(
            self::folderCacheKey($this->keyPrefix, $basePath, $folderName),
            fn (): array => $this->inner->discoverInFolder($basePath, $folderName),
        );
    }

    public static function cacheKey(string $keyPrefix, string $basePath, string $pattern): string
    {
        return $keyPrefix.':'.hash('xxh128', $basePath.'|'.$pattern);
    }

    public static function folderCacheKey(string $keyPrefix, string $basePath, string $folderName): string
    {
        // Distinct namespace ("folder:") so a folderName equal to a pattern
        // string cannot collide with a pattern cache entry.
        return $keyPrefix.':folder:'.hash('xxh128', $basePath.'|'.$folderName);
    }
}
