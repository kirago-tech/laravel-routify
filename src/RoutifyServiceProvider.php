<?php

declare(strict_types=1);

namespace Kirago\Routify;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\ServiceProvider;
use Kirago\Routify\Commands\CacheCommand;
use Kirago\Routify\Commands\ClearCommand;
use Kirago\Routify\Commands\ListCommand;
use Kirago\Routify\Commands\OptimizeCommand;
use Kirago\Routify\Contracts\RouteDiscoverer;
use Kirago\Routify\Discovery\CachedRouteDiscoverer;
use Kirago\Routify\Discovery\FilesystemRouteDiscoverer;

final class RoutifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/routify.php', 'routify');

        $this->app->singleton(RouteDiscoverer::class, function ($app): RouteDiscoverer {
            $discoverer = new FilesystemRouteDiscoverer;

            $config = $app->make(Config::class);

            if ($config->get('routify.cache.enabled')) {
                $cache = $app->make('cache')->store($config->get('routify.cache.store'));

                $discoverer = new CachedRouteDiscoverer(
                    $discoverer,
                    $cache,
                    (string) $config->get('routify.cache.key', 'routify:files'),
                );
            }

            return $discoverer;
        });

        $this->app->singleton(RoutifyManager::class);
    }

    /**
     * @throws BindingResolutionException
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/routify.php' => config_path('routify.php'),
            ], 'routify-config');

            $this->commands([
                ListCommand::class,
                CacheCommand::class,
                ClearCommand::class,
                OptimizeCommand::class,
            ]);
        }

        if (! $this->app->make(Config::class)->get('routify.auto_discover_on_boot')) {
            return;
        }

        // Escape hatch: when the operator is running a routify:* command, the
        // boot must NEVER trigger discovery — those commands exist precisely
        // to repair a broken discovery state (stale cache, renamed files,
        // unreachable cache backend), so they have to stay reachable even
        // when auto-discovery would otherwise crash the bootstrap.
        if ($this->app->runningInConsole()
            && self::isMaintenanceCommand((string) ($_SERVER['argv'][1] ?? ''))
        ) {
            return;
        }

        $this->app->make(RoutifyManager::class)->discover();
    }

    /**
     * Pure helper so the routing of routify:* commands stays unit-testable
     * without bootstrapping the framework.
     */
    public static function isMaintenanceCommand(string $command): bool
    {
        return str_starts_with($command, 'routify:');
    }
}
