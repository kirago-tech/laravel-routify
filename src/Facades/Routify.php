<?php

declare(strict_types=1);

namespace Kirago\Routify\Facades;

use Illuminate\Support\Facades\Facade;
use Kirago\Routify\RoutifyManager;
use Kirago\Routify\Support\RouteStackBuilder;

/**
 * @method static void discover()
 * @method static void discoverApi(?string $prefix = null)
 * @method static void discoverWeb(?string $prefix = null)
 * @method static void discoverConsole()
 * @method static void discoverChannels()
 * @method static RouteStackBuilder for(string $stackName)
 *
 * @see RoutifyManager
 */
final class Routify extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return RoutifyManager::class;
    }
}
