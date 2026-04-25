<?php

declare(strict_types=1);

use Kirago\Routify\Exceptions\InvalidConfigurationException;
use Kirago\Routify\Support\StackConfig;

it('builds a complete config from a fully populated array', function (): void {
    $stack = StackConfig::fromArray('api', [
        'enabled' => true,
        'pattern' => 'api*.php',
        'middleware' => ['api', 'throttle:60,1'],
        'prefix' => 'api',
        'name' => 'api.',
        'domain' => 'api.example.com',
    ]);

    expect($stack->name)->toBe('api')
        ->and($stack->enabled)->toBeTrue()
        ->and($stack->pattern)->toBe('api*.php')
        ->and($stack->middleware)->toBe(['api', 'throttle:60,1'])
        ->and($stack->prefix)->toBe('api')
        ->and($stack->namePrefix)->toBe('api.')
        ->and($stack->domain)->toBe('api.example.com');
});

it('applies sensible defaults when optional fields are missing', function (): void {
    $stack = StackConfig::fromArray('web', ['pattern' => 'web*.php']);

    expect($stack->enabled)->toBeTrue()
        ->and($stack->middleware)->toBe([])
        ->and($stack->prefix)->toBeNull()
        ->and($stack->namePrefix)->toBeNull()
        ->and($stack->domain)->toBeNull();
});

it('throws InvalidConfigurationException when pattern is missing', function (): void {
    StackConfig::fromArray('api', ['middleware' => ['api']]);
})->throws(InvalidConfigurationException::class, 'pattern');

it('throws InvalidConfigurationException when pattern is an empty string', function (): void {
    StackConfig::fromArray('api', ['pattern' => '']);
})->throws(InvalidConfigurationException::class);

it('throws InvalidConfigurationException when pattern is not a string', function (): void {
    StackConfig::fromArray('api', ['pattern' => ['api*.php']]);
})->throws(InvalidConfigurationException::class);

it('withPrefix returns a new instance and leaves the original untouched', function (): void {
    $original = StackConfig::fromArray('api', ['pattern' => 'api*.php', 'prefix' => 'api']);

    $modified = $original->withPrefix('api/v2');

    expect($modified)->not->toBe($original)
        ->and($modified->prefix)->toBe('api/v2')
        ->and($original->prefix)->toBe('api');
});

it('withMiddleware, withName, withDomain, withPattern all behave as immutable withers', function (): void {
    $stack = StackConfig::fromArray('api', ['pattern' => 'api*.php']);

    expect($stack->withMiddleware(['api', 'auth'])->middleware)->toBe(['api', 'auth'])
        ->and($stack->middleware)->toBe([])
        ->and($stack->withName('admin.')->namePrefix)->toBe('admin.')
        ->and($stack->withDomain('shop.example.com')->domain)->toBe('shop.example.com')
        ->and($stack->withPattern('admin*.php')->pattern)->toBe('admin*.php');
});
