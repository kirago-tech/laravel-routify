<?php

declare(strict_types=1);

namespace Kirago\Routify\Support;

use Kirago\Routify\Exceptions\InvalidConfigurationException;

final readonly class StackConfig
{
    /**
     * @param  list<string>  $middleware
     */
    public function __construct(
        public string $name,
        public bool $enabled,
        public string $pattern,
        public array $middleware,
        public ?string $prefix = null,
        public ?string $namePrefix = null,
        public ?string $domain = null,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(string $name, array $config): self
    {
        $pattern = $config['pattern'] ?? null;
        if (! is_string($pattern) || $pattern === '') {
            throw new InvalidConfigurationException(sprintf(
                'Stack "%s" must define a non-empty string "pattern" (e.g. "api*.php").',
                $name,
            ));
        }

        return new self(
            name: $name,
            enabled: (bool) ($config['enabled'] ?? true),
            pattern: $pattern,
            middleware: array_values((array) ($config['middleware'] ?? [])),
            prefix: $config['prefix'] ?? null,
            namePrefix: $config['name'] ?? null,
            domain: $config['domain'] ?? null,
        );
    }

    public function withPattern(string $pattern): self
    {
        return new self(
            $this->name, $this->enabled, $pattern, $this->middleware,
            $this->prefix, $this->namePrefix, $this->domain,
        );
    }

    public function withPrefix(?string $prefix): self
    {
        return new self(
            $this->name, $this->enabled, $this->pattern, $this->middleware,
            $prefix, $this->namePrefix, $this->domain,
        );
    }

    public function withName(?string $namePrefix): self
    {
        return new self(
            $this->name, $this->enabled, $this->pattern, $this->middleware,
            $this->prefix, $namePrefix, $this->domain,
        );
    }

    public function withDomain(?string $domain): self
    {
        return new self(
            $this->name, $this->enabled, $this->pattern, $this->middleware,
            $this->prefix, $this->namePrefix, $domain,
        );
    }

    /**
     * @param  list<string>  $middleware
     */
    public function withMiddleware(array $middleware): self
    {
        return new self(
            $this->name, $this->enabled, $this->pattern, array_values($middleware),
            $this->prefix, $this->namePrefix, $this->domain,
        );
    }
}
