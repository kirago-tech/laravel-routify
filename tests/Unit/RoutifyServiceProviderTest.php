<?php

declare(strict_types=1);

use Kirago\Routify\RoutifyServiceProvider;

// P0-A — The boot escape hatch identifies routify:* commands so they remain
// reachable when the cached discovery state is corrupt. Static + pure so it
// can be tested without bootstrapping the framework.

it('detects routify:* commands as maintenance commands', function (string $cmd): void {
    expect(RoutifyServiceProvider::isMaintenanceCommand($cmd))->toBeTrue();
})->with([
    'routify:clear',
    'routify:cache',
    'routify:optimize',
    'routify:list',
]);

it('does not flag unrelated artisan commands as maintenance', function (string $cmd): void {
    expect(RoutifyServiceProvider::isMaintenanceCommand($cmd))->toBeFalse();
})->with([
    '',
    'serve',
    'migrate',
    'cache:clear',
    'route:cache',
    'make:routify',
]);
