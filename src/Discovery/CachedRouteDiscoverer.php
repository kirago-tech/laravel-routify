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
        $key = self::cacheKey($this->keyPrefix, $basePath, $pattern);

        return $this->rememberValidated(
            $key,
            fn (): array => $this->inner->discover($basePath, $pattern),
        );
    }

    public function discoverInFolder(string $basePath, string $folderName): array
    {
        $key = self::folderCacheKey($this->keyPrefix, $basePath, $folderName);

        return $this->rememberValidated(
            $key,
            fn (): array => $this->inner->discoverInFolder($basePath, $folderName),
        );
    }

    public function forget(string $key): bool
    {
        return $this->cache->forget($key);
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

    /**
     * Get-validate-or-rescan. A cached entry that lists at least one path
     * that no longer exists on disk is treated as stale: it is forgotten
     * and the inner discoverer is consulted again. This is the auto-heal
     * mechanism that keeps the package usable after a deploy renames or
     * deletes a route file without anyone running `routify:clear`.
     *
     * Cost: O(n) is_file() calls per cache hit. Negligible vs. the cost of
     * a full Symfony Finder scan, which is exactly what this saves on hits.
     *
     * @param  callable(): list<string>  $producer
     * @return list<string>
     */
    private function rememberValidated(string $key, callable $producer): array
    {
        /** @var mixed $cached */
        $cached = $this->cache->get($key);

        if (is_array($cached) && self::allExist($cached)) {
            /** @var list<string> $cached */
            return $cached;
        }

        if ($cached !== null) {
            $this->cache->forget($key);
        }

        $fresh = $producer();
        $this->cache->forever($key, $fresh);

        return $fresh;
    }

    /**
     * @param  array<int|string, mixed>  $files
     */
    private static function allExist(array $files): bool
    {
        foreach ($files as $file) {
            if (! is_string($file) || ! is_file($file)) {
                return false;
            }
        }

        return true;
    }
}
