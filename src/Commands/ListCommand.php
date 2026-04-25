<?php

declare(strict_types=1);

namespace Kirago\Routify\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository as Config;
use Kirago\Routify\Contracts\RouteDiscoverer;
use Kirago\Routify\Support\StackConfig;

final class ListCommand extends Command
{
    /** @var string */
    protected $signature = 'routify:list {--stack= : Restrict the listing to a single stack}';

    /** @var string */
    protected $description = 'List every Routify-discovered route file, grouped by stack.';

    public function handle(RouteDiscoverer $discoverer, Config $config): int
    {
        $stacks = (array) $config->get('routify.stacks', []);
        $paths = array_values(array_filter((array) $config->get('routify.paths', []), 'is_string'));
        $filter = $this->option('stack');

        $rows = [];
        foreach ($stacks as $name => $raw) {
            $stack = StackConfig::fromArray((string) $name, (array) $raw);

            if (! $stack->enabled) {
                continue;
            }
            if ($filter !== null && $stack->name !== $filter) {
                continue;
            }

            foreach ($paths as $path) {
                foreach ($discoverer->discover($path, $stack->pattern) as $file) {
                    $rows[] = [
                        $stack->name,
                        $path,
                        ltrim(str_replace([$path, '\\'], ['', '/'], $file), '/'),
                        $stack->pattern,
                        $stack->middleware === [] ? '-' : implode(', ', $stack->middleware),
                        $stack->prefix ?? '-',
                    ];
                }
            }
        }

        if ($rows === []) {
            $this->components->warn('No Routify route files discovered.');

            return self::SUCCESS;
        }

        $this->table(
            ['Stack', 'Path', 'File', 'Pattern', 'Middleware', 'Prefix'],
            $rows,
        );

        return self::SUCCESS;
    }
}
