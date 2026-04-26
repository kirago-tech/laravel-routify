<?php

declare(strict_types=1);

namespace Kirago\Routify\Tests;

use Illuminate\Foundation\Application;
use Kirago\Routify\RoutifyServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [RoutifyServiceProvider::class];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('routify.paths', [__DIR__.'/fixtures/modules']);
        $app['config']->set('routify.auto_discover_on_boot', false);
        // Tests opt into the cache decorator explicitly; keep the global
        // Test default off so cache-disabled assertions stay valid.
        $app['config']->set('routify.cache.enabled', false);
    }
}
