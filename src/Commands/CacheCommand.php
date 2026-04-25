<?php

declare(strict_types=1);

namespace Kirago\Routify\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository as Config;
use Kirago\Routify\Contracts\RouteDiscoverer;
use Kirago\Routify\Support\StackConfig;

final class CacheCommand extends Command
{
    /** @var string */
    protected $signature = 'routify:cache';

    /** @var string */
    protected $description = "Warm Routify's discovery cache for every configured path and stack.";

    public function handle(RouteDiscoverer $discoverer, Config $config): int
    {
        if (! $config->get('routify.cache.enabled')) {
            $this->components->error('Routify cache is disabled. Set ROUTIFY_CACHE=true (or routify.cache.enabled = true) and retry.');

            return self::FAILURE;
        }

        $stacks = (array) $config->get('routify.stacks', []);
        $paths = array_values(
            array_filter((array) $config->get('routify.paths', []), 'is_string'));

        $rows = [];
        foreach ($stacks as $name => $raw) {
            $stack = StackConfig::fromArray((string) $name, (array) $raw);
            if (! $stack->enabled) {
                continue;
            }

            $count = 0;
            foreach ($paths as $path) {
                $count += count($discoverer->discover($path, $stack->pattern));
            }
            $rows[] = [$stack->name, $count];
        }

        $this->components->info('Routify discovery cache primed.');
        if ($rows !== []) {
            $this->table(['Stack', 'Files cached'], $rows);
        }

        return self::SUCCESS;
    }
}
