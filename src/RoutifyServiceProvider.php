<?php

declare(strict_types=1);

namespace Kirago\Routify;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\ServiceProvider;
use Kirago\Routify\Commands\CacheCommand;
use Kirago\Routify\Commands\ClearCommand;
use Kirago\Routify\Commands\ListCommand;
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
            ]);
        }

        if ($this->app->make(Config::class)->get('routify.auto_discover_on_boot')) {
            $this->app->make(RoutifyManager::class)->discover();
        }
    }
}
