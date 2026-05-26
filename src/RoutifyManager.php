<?php

declare(strict_types=1);

namespace Kirago\Routify;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Routing\Router;
use Kirago\Routify\Contracts\RouteDiscoverer;
use Kirago\Routify\Contracts\StackLoader;
use Kirago\Routify\Exceptions\InvalidConfigurationException;
use Kirago\Routify\Exceptions\RoutifyException;
use Kirago\Routify\Support\RouteStackBuilder;
use Kirago\Routify\Support\StackConfig;

final class RoutifyManager implements StackLoader
{
    /** @var array<string, StackConfig>|null */
    private ?array $stacksCache = null;

    public function __construct(
        private readonly RouteDiscoverer $discoverer,
        private readonly Router $router,
        private readonly Config $config,
    ) {}

    public function discover(): void
    {
        foreach ($this->stacks() as $stack) {
            if ($stack->enabled) {
                $this->loadStack($stack);
            }
        }
    }

    public function discoverWeb(?string $prefix = null): void
    {
        $this->loadOne('web', $prefix);
    }

    public function discoverApi(?string $prefix = null): void
    {
        $this->loadOne('api', $prefix);
    }

    public function discoverConsole(): void
    {
        $this->loadStack($this->stack('console'));
    }

    public function discoverChannels(): void
    {
        $this->loadStack($this->stack('channels'));
    }

    public function for(string $stackName): RouteStackBuilder
    {
        return new RouteStackBuilder($this, $this->stack($stackName));
    }

    public function loadStack(StackConfig $stack, ?array $pathsOverride = null): void
    {
        $files = [];
        foreach ($pathsOverride ?? $this->paths() as $basePath) {
            // A stack without pattern relies on by-folder discovery alone, but
            // a misconfigured paths[] still has to fail loudly (cf. v1.0 contract).
            if ($stack->pattern === null && ! is_dir($basePath)) {
                throw new RoutifyException(sprintf(
                    'Routify cannot scan "%s": the directory does not exist. Check your routify.paths configuration.',
                    $basePath,
                ));
            }

            if ($stack->pattern !== null) {
                foreach ($this->discoverer->discover($basePath, $stack->pattern) as $file) {
                    $files[$file] = true;
                }
            }

            foreach ($this->discoverer->discoverInFolder($basePath, $stack->name) as $file) {
                $files[$file] = true;
            }
        }

        if ($files === []) {
            return;
        }

        $touchedRouter = false;
        foreach (array_keys($files) as $file) {
            $touchedRouter = $this->registerRouteFile($stack, $file) || $touchedRouter;
        }

        // Routes registered via ->name() inside a group prefix are added to
        // the collection before their final name exists. Rebuild the name
        // lookup once per loadStack call so route('api.users.index') and
        // Route::has() find them — refreshing per-file would be O(n²).
        if ($touchedRouter) {
            $this->router->getRoutes()->refreshNameLookups();
        }
    }

    private function loadOne(string $name, ?string $prefix): void
    {
        $stack = $this->stack($name);
        $this->loadStack($prefix !== null ? $stack->withPrefix($prefix) : $stack);
    }

    private function registerRouteFile(StackConfig $stack, string $file): bool
    {
        if ($stack->context !== StackConfig::CONTEXT_HTTP) {
            require_once $file;

            return false;
        }

        $group = $this->router;

        if ($stack->middleware !== []) {
            $group = $group->middleware($stack->middleware);
        }
        if ($stack->prefix !== null) {
            $group = $group->prefix($stack->prefix);
        }
        if ($stack->namePrefix !== null) {
            $group = $group->name($stack->namePrefix);
        }
        if ($stack->domain !== null) {
            $group = $group->domain($stack->domain);
        }

        $group->group($file);

        return true;
    }

    /**
     * @return array<string, StackConfig>
     */
    private function stacks(): array
    {
        if ($this->stacksCache !== null) {
            return $this->stacksCache;
        }

        $raw = $this->config->get('routify.stacks', []);
        if (! is_array($raw)) {
            throw new InvalidConfigurationException('routify.stacks must be an array.');
        }

        $stacks = [];
        foreach ($raw as $name => $config) {
            $stacks[(string) $name] = StackConfig::fromArray((string) $name, (array) $config);
        }

        return $this->stacksCache = $stacks;
    }

    private function stack(string $name): StackConfig
    {
        $stacks = $this->stacks();
        if (! isset($stacks[$name])) {
            throw new InvalidConfigurationException(sprintf(
                'Routify stack "%s" is not defined in routify.stacks.',
                $name,
            ));
        }

        return $stacks[$name];
    }

    /**
     * @return list<string>
     */
    private function paths(): array
    {
        $paths = $this->config->get('routify.paths', []);

        return array_values(array_filter((array) $paths, 'is_string'));
    }
}
