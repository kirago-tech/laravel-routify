<?php

declare(strict_types=1);

use Kirago\Routify\Contracts\StackLoader;
use Kirago\Routify\Support\RouteStackBuilder;
use Kirago\Routify\Support\StackConfig;

function spyLoader(): StackLoader
{
    return new class implements StackLoader
    {
        public ?StackConfig $received = null;

        /** @var list<string>|null */
        public ?array $receivedPaths = null;

        public int $calls = 0;

        public function loadStack(StackConfig $stack, ?array $pathsOverride = null): void
        {
            $this->calls++;
            $this->received = $stack;
            $this->receivedPaths = $pathsOverride;
        }
    };
}

function baseStack(): StackConfig
{
    return StackConfig::fromArray('api', [
        'pattern' => 'api*.php',
        'middleware' => ['api'],
        'prefix' => 'api',
    ]);
}

it('forwards the original stack with a null paths override when load() is called bare', function (): void {
    $loader = spyLoader();

    (new RouteStackBuilder($loader, baseStack()))->load();

    expect($loader->calls)->toBe(1)
        ->and($loader->received->pattern)->toBe('api*.php')
        ->and($loader->received->prefix)->toBe('api')
        ->and($loader->receivedPaths)->toBeNull();
});

it('applies fluent withers to the stack passed to the loader', function (): void {
    $loader = spyLoader();

    (new RouteStackBuilder($loader, baseStack()))
        ->withPrefix('api/v2')
        ->withName('api.v2.')
        ->withDomain('api.example.com')
        ->matching('api-v2*.php')
        ->load();

    expect($loader->received->prefix)->toBe('api/v2')
        ->and($loader->received->namePrefix)->toBe('api.v2.')
        ->and($loader->received->domain)->toBe('api.example.com')
        ->and($loader->received->pattern)->toBe('api-v2*.php');
});

it('accumulates paths via in() and forwards them as the override list', function (): void {
    $loader = spyLoader();

    (new RouteStackBuilder($loader, baseStack()))
        ->in('/app/Modules')
        ->in('/app/Features')
        ->load();

    expect($loader->receivedPaths)->toBe(['/app/Modules', '/app/Features']);
});

it('returns the same builder instance from every fluent method (chainable)', function (): void {
    $builder = new RouteStackBuilder(spyLoader(), baseStack());

    expect($builder->in('/a'))->toBe($builder)
        ->and($builder->withPrefix('p'))->toBe($builder)
        ->and($builder->withName('n.'))->toBe($builder)
        ->and($builder->withDomain('d'))->toBe($builder)
        ->and($builder->withMiddleware(['m']))->toBe($builder)
        ->and($builder->matching('*.php'))->toBe($builder);
});

it('withMiddleware accepts a string and wraps it in a single-element list', function (): void {
    $loader = spyLoader();

    (new RouteStackBuilder($loader, baseStack()))
        ->withMiddleware('auth')
        ->load();

    expect($loader->received->middleware)->toBe(['auth']);
});

it('withMiddleware accepts an array and replaces the stack middleware', function (): void {
    $loader = spyLoader();

    (new RouteStackBuilder($loader, baseStack()))
        ->withMiddleware(['api', 'throttle:60,1'])
        ->load();

    expect($loader->received->middleware)->toBe(['api', 'throttle:60,1']);
});
