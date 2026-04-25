<?php

declare(strict_types=1);

namespace Kirago\Routify\Contracts;

use Kirago\Routify\Support\StackConfig;

interface StackLoader
{
    /**
     * Register every route file matching $stack->pattern under each base path.
     *
     * @param  list<string>|null  $pathsOverride  if null, falls back to the configured routify.paths
     */
    public function loadStack(StackConfig $stack, ?array $pathsOverride = null): void;
}
