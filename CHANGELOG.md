# Changelog

All notable changes to `kirago/laravel-routify` are documented here.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.1.0] - 2026-05-26

### Added

- **Folder-based discovery.** A `.php` file under a `paths[]` directory whose
  relative path contains a segment exactly named after a stack key (e.g.
  `api/`, `web/`, `admin/`) is now discovered for that stack — at any depth.
  Filename-based patterns (`api*.php`, `web*.php`, …) continue to work
  unchanged; the two modes coexist, and a file matched by both is registered
  exactly once. Dropping `routes/api/billing.php` is enough — no rename
  required. See `docs/adr/0013-decouverte-par-dossier.md`.
- `RouteDiscoverer::discoverInFolder(string $basePath, string $folderName)`
  contract method, implemented by `FilesystemRouteDiscoverer` (segment-exact
  match, alphabetically sorted, empty array when `$basePath` is missing) and
  `CachedRouteDiscoverer` (namespaced cache keys via the new
  `folderCacheKey()` helper — no collision with pattern cache keys).
- `routify:list` rows now include folder-discovered files with `(by-folder)`
  in the Pattern column. `routify:cache` warms folder cache entries.
  `routify:clear` forgets them. `routify:optimize` is therefore complete
  without any signature change.

### Changed

- `StackConfig::$pattern` becomes nullable (`?string`). A stack can now be
  declared without `pattern` and rely entirely on the folder convention.
  `StackConfig::fromArray()` no longer throws when `pattern` is missing or an
  empty string — both are normalised to `null`. A non-string `pattern` still
  throws (`InvalidConfigurationException`). This is a *relaxation* of an
  existing guard, not a breaking change: every v1.0 config remains valid.

### Compatibility

- **Strict superset guarantee.** For any v1.0 configuration `C`,
  `files_loaded_by_v1.1(C) ⊇ files_loaded_by_v1.0(C)`. Every glob pattern
  configured in v1.0 (`web*.php`, `api*.php`, custom `*api*.php` …) continues
  to work identically. The v1.0 Pest suite passes unchanged.
- **Behavior notice.** A folder *under a scanned `paths[]`* whose name
  matches a declared stack key (`api/`, `web/`, …) and which contains
  non-route `.php` files will see those files newly discovered as routes.
  If you have non-route PHP under such folders, either narrow your `paths`
  config or rename the folders. This is the only case where v1.1 loads a
  file v1.0 did not.

### Requirements

- PHP `^8.2` (PHP 8.5 added to the CI matrix).
- Laravel `^11.0 || ^12.0 || ^13.0`.
- Testbench `^9.0 || ^10.0 || ^11.0` (dev).
- Symfony Finder `^7.0 || ^8.0`.

## [1.0.0] - 2026-04-26

### Added

- `Routify` facade exposing `discover()`, `discoverWeb()`, `discoverApi()`,
  `discoverConsole()`, `discoverChannels()` and `for()`.
- `RoutifyManager` orchestrator wiring the discoverer, the configured stacks
  and the route registrar; bypasses `Route::group()` for the `console` and
  `channels` stacks (`require_once` into the right context) and refreshes the
  route name lookup so `Route::has()` / `route()` see chained `->name()` calls.
- `RouteStackBuilder` fluent builder driven by a small `StackLoader` contract,
  with `in()`, `withPrefix()`, `withName()`, `withDomain()`, `withMiddleware()`,
  `matching()` and a terminal `load()`.
- `StackConfig` value object (`final readonly`) with a strict
  `fromArray()` factory and immutable witherers.
- `FilesystemRouteDiscoverer` backed by Symfony Finder, with an explicit
  base-path guard that throws `RoutifyException` instead of leaking
  `DirectoryNotFoundException`.
- `CachedRouteDiscoverer` decorator using `rememberForever` and a hashed key
  per `(basePath, pattern)` pair to avoid stack-to-stack collisions.
- `routify:list`, `routify:cache` and `routify:clear` Artisan commands.
- `RoutifyServiceProvider` merging the package config, registering the
  manager singleton, wiring the cache decorator on demand, publishing the
  config under the `routify-config` tag and triggering automatic discovery
  on boot when configured.
- `docs/adr/` directory holding twelve French-language Architecture Decision
  Records covering every structural choice shipped with 1.0.0: layered
  architecture, Symfony Finder, configurable glob patterns and stacks,
  console/channels bypass, decorator-based cache with hashed keys,
  `final readonly` value objects, extracted `StackLoader` contract, the
  `refreshNameLookups()` workaround, opt-in auto-discovery, explicit
  exception policy, the three Artisan commands and Git-tag versioning.
  Plus an index README and a 0000 template for future ADRs.
- 46 tests / 115 assertions across unit (Discovery, Support) and Feature
  (Discovery, Facade, FluentBuilder, Commands, Cache) suites via Orchestra
  Testbench and Pest 3.

### Requirements

- PHP `^8.2`
- Laravel `^11.0 || ^12.0`

[Unreleased]: https://github.com/kirago-tech/laravel-routify/compare/v1.1.0...HEAD
[1.1.0]: https://github.com/kirago-tech/laravel-routify/releases/tag/v1.1.0
[1.0.0]: https://github.com/kirago-tech/laravel-routify/releases/tag/v1.0.0