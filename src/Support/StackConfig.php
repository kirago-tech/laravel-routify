<?php

declare(strict_types=1);

namespace Kirago\Routify\Support;

use Kirago\Routify\Exceptions\InvalidConfigurationException;

final readonly class StackConfig
{
    public const CONTEXT_HTTP = 'http';

    public const CONTEXT_CLI = 'cli';

    public const CONTEXT_BROADCAST = 'broadcast';

    private const ALLOWED_CONTEXTS = [self::CONTEXT_HTTP, self::CONTEXT_CLI, self::CONTEXT_BROADCAST];

    /**
     * @param  list<string>  $middleware
     */
    public function __construct(
        public string $name,
        public bool $enabled,
        public ?string $pattern,
        public array $middleware,
        public ?string $prefix = null,
        public ?string $namePrefix = null,
        public ?string $domain = null,
        public string $context = self::CONTEXT_HTTP,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(string $name, array $config): self
    {
        $pattern = self::normalizePattern($name, $config['pattern'] ?? null);
        $middleware = self::normalizeMiddleware($name, $config['middleware'] ?? []);
        $context = self::resolveContext($name, $config['context'] ?? null);

        return new self(
            name: $name,
            enabled: (bool) ($config['enabled'] ?? true),
            pattern: $pattern,
            middleware: $middleware,
            prefix: self::nullableString($config['prefix'] ?? null),
            namePrefix: self::nullableString($config['name'] ?? null),
            domain: self::nullableString($config['domain'] ?? null),
            context: $context,
        );
    }

    public function withPattern(?string $pattern): self
    {
        return new self(
            $this->name, $this->enabled, $pattern, $this->middleware,
            $this->prefix, $this->namePrefix, $this->domain, $this->context,
        );
    }

    public function withPrefix(?string $prefix): self
    {
        return new self(
            $this->name, $this->enabled, $this->pattern, $this->middleware,
            $prefix, $this->namePrefix, $this->domain, $this->context,
        );
    }

    public function withName(?string $namePrefix): self
    {
        return new self(
            $this->name, $this->enabled, $this->pattern, $this->middleware,
            $this->prefix, $namePrefix, $this->domain, $this->context,
        );
    }

    public function withDomain(?string $domain): self
    {
        return new self(
            $this->name, $this->enabled, $this->pattern, $this->middleware,
            $this->prefix, $this->namePrefix, $domain, $this->context,
        );
    }

    /**
     * @param  list<string>  $middleware
     */
    public function withMiddleware(array $middleware): self
    {
        return new self(
            $this->name, $this->enabled, $this->pattern, array_values($middleware),
            $this->prefix, $this->namePrefix, $this->domain, $this->context,
        );
    }

    private static function normalizePattern(string $name, mixed $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if (! is_string($raw)) {
            throw new InvalidConfigurationException(sprintf(
                'Stack "%s" pattern must be a string or null, %s given.',
                $name,
                get_debug_type($raw),
            ));
        }

        return $raw;
    }

    /**
     * @return list<string>
     */
    private static function normalizeMiddleware(string $name, mixed $raw): array
    {
        if ($raw === null || $raw === []) {
            return [];
        }

        if (! is_array($raw)) {
            throw new InvalidConfigurationException(sprintf(
                'Stack "%s" middleware must be a list of strings, %s given.',
                $name,
                get_debug_type($raw),
            ));
        }

        $normalized = [];
        foreach ($raw as $entry) {
            if (! is_string($entry) || $entry === '') {
                throw new InvalidConfigurationException(sprintf(
                    'Stack "%s" middleware entries must be non-empty strings, %s given.',
                    $name,
                    get_debug_type($entry),
                ));
            }
            $normalized[] = $entry;
        }

        return $normalized;
    }

    private static function resolveContext(string $name, mixed $explicit): string
    {
        if ($explicit !== null) {
            if (! is_string($explicit) || ! in_array($explicit, self::ALLOWED_CONTEXTS, true)) {
                throw new InvalidConfigurationException(sprintf(
                    'Stack "%s" context must be one of: %s.',
                    $name,
                    implode(', ', self::ALLOWED_CONTEXTS),
                ));
            }

            return $explicit;
        }

        return match ($name) {
            'console' => self::CONTEXT_CLI,
            'channels' => self::CONTEXT_BROADCAST,
            default => self::CONTEXT_HTTP,
        };
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_string($value) ? $value : (string) $value;
    }
}
