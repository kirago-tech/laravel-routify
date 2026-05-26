<?php

declare(strict_types=1);

use Kirago\Routify\Discovery\FilesystemRouteDiscoverer;
use Kirago\Routify\Exceptions\RoutifyException;

beforeEach(function (): void {
    $this->tempDir = sys_get_temp_dir().'/routify-test-'.bin2hex(random_bytes(6));
    mkdir($this->tempDir, 0o777, true);
});

afterEach(function (): void {
    if (is_dir($this->tempDir)) {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->tempDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($it as $entry) {
            $entry->isDir()
                ? rmdir($entry->getPathname())
                : unlink($entry->getPathname());
        }
        rmdir($this->tempDir);
    }
});

function touchFile(string $path): string
{
    $dir = dirname($path);
    if (! is_dir($dir)) {
        mkdir($dir, 0o777, true);
    }
    file_put_contents($path, "<?php\n");

    return str_replace('\\', '/', $path);
}

it('discovers a file at the basePath root', function (): void {
    $file = touchFile($this->tempDir.'/api.php');

    $found = (new FilesystemRouteDiscoverer)->discover($this->tempDir, 'api*.php');

    expect($found)->toBe([$file]);
});

it('discovers files in nested subdirectories (recursive scan)', function (): void {
    $deep = touchFile($this->tempDir.'/ModuleA/Routes/sub/api.php');

    $found = (new FilesystemRouteDiscoverer)->discover($this->tempDir, 'api*.php');

    expect($found)->toBe([$deep]);
});

it('respects the glob pattern api*.php (matches api.php and api-v2.php, not web.php or console.php)', function (): void {
    touchFile($this->tempDir.'/Modules/A/api.php');
    touchFile($this->tempDir.'/Modules/A/api-v2.php');
    touchFile($this->tempDir.'/Modules/A/web.php');
    touchFile($this->tempDir.'/Modules/B/console.php');

    $found = (new FilesystemRouteDiscoverer)->discover($this->tempDir, 'api*.php');

    expect($found)
        ->toHaveCount(2)
        ->each->toEndWith('.php')
        ->and(implode('|', $found))->toContain('api.php', 'api-v2.php')
        ->not->toContain('web.php')
        ->not->toContain('console.php');
});

it('returns every PHP file when the pattern is *.php', function (): void {
    touchFile($this->tempDir.'/Modules/A/api.php');
    touchFile($this->tempDir.'/Modules/A/web.php');
    touchFile($this->tempDir.'/Modules/B/console.php');
    touchFile($this->tempDir.'/Modules/B/channels.php');

    $found = (new FilesystemRouteDiscoverer)->discover($this->tempDir, '*.php');

    expect($found)->toHaveCount(4);
});

it('returns the result sorted alphabetically (deterministic)', function (): void {
    // Created in non-alphabetical order on purpose.
    touchFile($this->tempDir.'/zeta/api.php');
    touchFile($this->tempDir.'/alpha/api.php');
    touchFile($this->tempDir.'/middle/api.php');

    $found = (new FilesystemRouteDiscoverer)->discover($this->tempDir, 'api*.php');
    $sorted = $found;
    sort($sorted);

    expect($found)->toBe($sorted);
});

it('throws RoutifyException when basePath does not exist', function (): void {
    $missing = $this->tempDir.'/does-not-exist';

    (new FilesystemRouteDiscoverer)->discover($missing, 'api*.php');
})->throws(RoutifyException::class);

it('returns an empty array when no file matches the pattern', function (): void {
    touchFile($this->tempDir.'/web.php');
    touchFile($this->tempDir.'/console.php');

    $found = (new FilesystemRouteDiscoverer)->discover($this->tempDir, 'api*.php');

    expect($found)->toBe([]);
});

// ---------------------------------------------------------------------------
// discoverInFolder (1.1 — folder-based discovery)
// ---------------------------------------------------------------------------

it('discoverInFolder finds every .php file directly under <basePath>/<folderName>/', function (): void {
    $billing = touchFile($this->tempDir.'/api/billing.php');
    $users = touchFile($this->tempDir.'/api/users.php');
    touchFile($this->tempDir.'/web/dashboard.php'); // different folder, must be ignored

    $found = (new FilesystemRouteDiscoverer)->discoverInFolder($this->tempDir, 'api');

    expect($found)->toBe([$billing, $users]);
});

it('discoverInFolder finds files when the named folder is nested deep in the path', function (): void {
    $billing = touchFile($this->tempDir.'/Modules/Blog/Routes/api/billing.php');
    $orders = touchFile($this->tempDir.'/Modules/Blog/Routes/api/v2/orders.php');

    $found = (new FilesystemRouteDiscoverer)->discoverInFolder($this->tempDir, 'api');

    expect($found)->toBe([$billing, $orders]);
});

it('discoverInFolder matches segment names exactly (not as substring)', function (): void {
    // "apiary" must NOT match folder name "api" — segment-exact, not substring.
    touchFile($this->tempDir.'/Modules/apiary/honey.php');
    $real = touchFile($this->tempDir.'/Modules/api/users.php');

    $found = (new FilesystemRouteDiscoverer)->discoverInFolder($this->tempDir, 'api');

    expect($found)->toBe([$real]);
});

it('discoverInFolder returns an empty array when no ancestor folder is named like that', function (): void {
    touchFile($this->tempDir.'/Modules/Blog/Routes/web.php');
    touchFile($this->tempDir.'/Modules/Blog/api.php'); // file at root, no api/ ancestor

    $found = (new FilesystemRouteDiscoverer)->discoverInFolder($this->tempDir, 'api');

    expect($found)->toBe([]);
});

it('discoverInFolder returns an empty array (no exception) when basePath does not exist', function (): void {
    $missing = $this->tempDir.'/does-not-exist';

    $found = (new FilesystemRouteDiscoverer)->discoverInFolder($missing, 'api');

    expect($found)->toBe([]);
});

it('discoverInFolder ignores non-php files inside the named folder', function (): void {
    $php = touchFile($this->tempDir.'/api/users.php');
    touchFile($this->tempDir.'/api/README.md');
    touchFile($this->tempDir.'/api/data.json');

    $found = (new FilesystemRouteDiscoverer)->discoverInFolder($this->tempDir, 'api');

    expect($found)->toBe([$php]);
});

it('discoverInFolder returns the result sorted alphabetically', function (): void {
    touchFile($this->tempDir.'/api/zebra.php');
    touchFile($this->tempDir.'/api/alpha.php');
    touchFile($this->tempDir.'/api/middle.php');

    $found = (new FilesystemRouteDiscoverer)->discoverInFolder($this->tempDir, 'api');
    $sorted = $found;
    sort($sorted);

    expect($found)->toBe($sorted);
});
