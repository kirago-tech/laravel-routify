<?php

declare(strict_types=1);

namespace Kirago\Routify\Discovery;

use Kirago\Routify\Contracts\RouteDiscoverer;
use Kirago\Routify\Exceptions\RoutifyException;
use Symfony\Component\Finder\Finder;

final class FilesystemRouteDiscoverer implements RouteDiscoverer
{
    public function discover(string $basePath, string $pattern): array
    {
        if (! is_dir($basePath)) {
            throw new RoutifyException(sprintf(
                'Routify cannot scan "%s": the directory does not exist. Check your routify.paths configuration.',
                $basePath,
            ));
        }

        $finder = (new Finder)
            ->files()
            ->in($basePath)
            ->name($pattern);

        $files = [];
        foreach ($finder as $file) {
            $files[] = self::normalize($file->getPathname());
        }

        sort($files);

        return array_values($files);
    }

    private static function normalize(string $path): string
    {
        return str_replace('\\', '/', $path);
    }
}
