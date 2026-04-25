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

    public static function cacheKey(string $keyPrefix, string $basePath, string $pattern): string
    {
        return $keyPrefix.':'.hash('xxh128', $basePath.'|'.$pattern);
    }
}
