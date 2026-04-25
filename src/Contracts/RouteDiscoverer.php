<?php

declare(strict_types=1);

namespace Kirago\Routify\Contracts;

interface RouteDiscoverer
{
    /**
     * Return the list of .php files matching $pattern under $basePath (recursive).
     *
     * @return list<string> absolute paths, alphabetically sorted (deterministic).
     */
    public function discover(string $basePath, string $pattern): array;
}
