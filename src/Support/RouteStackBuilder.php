<?php

declare(strict_types=1);

namespace Kirago\Routify\Support;

use Kirago\Routify\Contracts\StackLoader;

final class RouteStackBuilder
{
    /** @var list<string>|null */
    private ?array $pathsOverride = null;

    public function __construct(
        private readonly StackLoader $loader,
        private StackConfig $stack,
    ) {}

    public function in(string $path): self
    {
        $this->pathsOverride ??= [];
        $this->pathsOverride[] = $path;

        return $this;
    }

    public function withPrefix(?string $prefix): self
    {
        $this->stack = $this->stack->withPrefix($prefix);

        return $this;
    }

    public function withName(?string $namePrefix): self
    {
        $this->stack = $this->stack->withName($namePrefix);

        return $this;
    }

    public function withDomain(?string $domain): self
    {
        $this->stack = $this->stack->withDomain($domain);

        return $this;
    }

    /**
     * @param  list<string>|string  $middleware
     */
    public function withMiddleware(array|string $middleware): self
    {
        $this->stack = $this->stack->withMiddleware(is_array($middleware) ? $middleware : [$middleware]);

        return $this;
    }

    public function matching(string $pattern): self
    {
        $this->stack = $this->stack->withPattern($pattern);

        return $this;
    }

    public function load(): void
    {
        $this->loader->loadStack($this->stack, $this->pathsOverride);
    }
}
