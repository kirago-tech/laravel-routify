# Changelog

All notable changes to `kirago/laravel-routify` are documented here.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
- 46 tests / 115 assertions across unit (Discovery, Support) and Feature
  (Discovery, Facade, FluentBuilder, Commands, Cache) suites via Orchestra
  Testbench and Pest 3.

### Requirements

- PHP `^8.2`
- Laravel `^11.0 || ^12.0`

[Unreleased]: https://github.com/kirago-tech/laravel-routify/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/kirago-tech/laravel-routify/releases/tag/v1.0.0