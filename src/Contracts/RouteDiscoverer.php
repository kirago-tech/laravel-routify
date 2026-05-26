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

    /**
     * Return every .php file under $basePath whose relative path includes at
     * least one segment exactly equal to $folderName (matched as a whole
     * segment, not as a substring — "api" matches "Blog/Routes/api/foo.php"
     * but not "apiary/foo.php").
     *
     * Returns an empty array when $basePath does not exist or no file matches.
     * Unlike discover(), this method does NOT throw when $basePath is missing.
     *
     * @return list<string> absolute paths, alphabetically sorted (deterministic).
     */
    public function discoverInFolder(string $basePath, string $folderName): array;
}
