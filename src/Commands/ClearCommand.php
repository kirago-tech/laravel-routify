<?php

declare(strict_types=1);

namespace Kirago\Routify\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository as Config;
use Kirago\Routify\Discovery\CachedRouteDiscoverer;
use Kirago\Routify\Support\StackConfig;

final class ClearCommand extends Command
{
    /** @var string */
    protected $signature = 'routify:clear';

    /** @var string */
    protected $description = "Forget Routify's discovery cache.";

    public function handle(CacheFactory $cache, Config $config): int
    {
        if (! $config->get('routify.cache.enabled')) {
            $this->components->info('Routify cache is disabled — nothing to clear.');

            return self::SUCCESS;
        }

        $store = $cache->store($config->get('routify.cache.store'));
        $prefix = (string) $config->get('routify.cache.key', 'routify:files');
        $stacks = (array) $config->get('routify.stacks', []);
        $paths = array_values(array_filter((array) $config->get('routify.paths', []), 'is_string'));

        $forgotten = 0;
        foreach ($stacks as $name => $raw) {
            $stack = StackConfig::fromArray((string) $name, (array) $raw);
            foreach ($paths as $path) {
                if ($stack->pattern !== null) {
                    $patternKey = CachedRouteDiscoverer::cacheKey($prefix, $path, $stack->pattern);
                    if ($store->forget($patternKey)) {
                        $forgotten++;
                    }
                }
                $folderKey = CachedRouteDiscoverer::folderCacheKey($prefix, $path, $stack->name);
                if ($store->forget($folderKey)) {
                    $forgotten++;
                }
            }
        }

        $this->components->info(sprintf('Routify cache cleared (%d %s forgotten).', $forgotten, $forgotten === 1 ? 'key' : 'keys'));

        return self::SUCCESS;
    }
}
