<?php

declare(strict_types=1);

use Kirago\Routify\Facades\Routify;
use Kirago\Routify\RoutifyManager;
use Kirago\Routify\Support\RouteStackBuilder;

it('resolves the underlying RoutifyManager from the container', function (): void {
    expect(Routify::getFacadeRoot())->toBeInstanceOf(RoutifyManager::class);
});

it('exposes a fluent builder via Routify::for()', function (): void {
    expect(Routify::for('api'))->toBeInstanceOf(RouteStackBuilder::class);
});
