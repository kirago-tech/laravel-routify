<?php

declare(strict_types=1);

namespace Kirago\Routify;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Routing\Router;
use Kirago\Routify\Contracts\RouteDiscoverer;
use Kirago\Routify\Contracts\StackLoader;
use Kirago\Routify\Exceptions\InvalidConfigurationException;
use Kirago\Routify\Support\RouteStackBuilder;
use Kirago\Routify\Support\StackConfig;

final class RoutifyManager implements StackLoader
{
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
            foreach ($this->discoverer->discover($basePath, $stack->pattern) as $file) {
                $files[$file] = true;
            }
        }

        foreach (array_keys($files) as $file) {
            $this->registerRouteFile($stack, $file);
        }
    }

    private function loadOne(string $name, ?string $prefix): void
    {
        $stack = $this->stack($name);
        $this->loadStack($prefix !== null ? $stack->withPrefix($prefix) : $stack);
    }

    private function registerRouteFile(StackConfig $stack, string $file): void
    {
        if ($stack->name === 'console' || $stack->name === 'channels') {
            require_once $file;

            return;
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

        // Routes registered via ->name() inside a group prefix are added to
        // the collection before their final name exists. Rebuild the name
        // lookup so route('api.users.index') / Route::has() find them.
        $this->router->getRoutes()->refreshNameLookups();
    }

    /**
     * @return array<string, StackConfig>
     */
    private function stacks(): array
    {
        $raw = $this->config->get('routify.stacks', []);
        if (! is_array($raw)) {
            throw new InvalidConfigurationException('routify.stacks must be an array.');
        }

        $stacks = [];
        foreach ($raw as $name => $config) {
            $stacks[(string) $name] = StackConfig::fromArray((string) $name, (array) $config);
        }

        return $stacks;
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
