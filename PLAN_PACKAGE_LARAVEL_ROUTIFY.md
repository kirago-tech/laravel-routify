# Plan d'implémentation — Package `kirago/laravel-routify`

> **Objet** : créer un package Laravel autonome qui auto-découvre et enregistre les fichiers de routes répartis dans plusieurs sous-dossiers d'une application — utile pour toute architecture où les routes ne vivent pas uniquement dans `routes/web.php` et `routes/api.php` (apps modulaires, plugins, packages internes, micro-services en monolithe, etc.).
>
> **Audience** : agent Claude Code exécutant ce plan séquentiellement, **dans un repo neuf et vide**. Aucune connaissance préalable d'un projet existant n'est requise — toutes les conventions, conteneurs et chemins sont précisés dans le plan.
>
> **Version cible** : `1.0.0` — Laravel 11 & 12, PHP 8.2+.
>
> **Repo Git distant** : `git@github.com:kirago-tech/laravel-routify.git`

---

## 0. Contexte & Vision

### Le problème
Laravel ne sait charger automatiquement que `routes/web.php`, `routes/api.php`, `routes/console.php` et `routes/channels.php`. Toute autre architecture (modulaire, par feature, par bounded context, par plugin) oblige le développeur à enregistrer manuellement chaque fichier de routes dans un `RouteServiceProvider`, ce qui devient pénible quand le nombre de modules grandit.

### Ce que `routify` apporte
- **Auto-découverte** récursive de fichiers de routes selon des patterns glob (`api*.php`, `web*.php`, `*.php`...).
- **Multi-paths** : on peut scanner plusieurs racines (`app/Modules`, `app/Features`, `packages/*/routes`...).
- **Stacks configurables** : web / api / console / channels, ou stacks custom (admin, gateway, internal-api...).
- **Cache filesystem** : la liste des fichiers découverts peut être mise en cache pour éviter le scan disque à chaque boot en production.
- **Fluent builder** : pour les cas avancés (override d'un middleware, d'un domaine, d'un préfixe).
- **Zero-magic par défaut** : opt-in explicite, l'utilisateur appelle `Routify::discoverApi()` plutôt que de subir une découverte invisible.

### Principes de design
| Principe | Implémentation |
|---|---|
| **Configurable, pas opinionated** | Aucun chemin, aucun nom de dossier n'est codé en dur. Tout passe par `config/routify.php`. |
| **Patterns glob > conventions de noms** | Au lieu de chercher uniquement `api.php`, on cherche `api*.php` (matche `api.php`, `api-v2.php`, `api-internal.php`...). L'utilisateur reste maître via son pattern. |
| **Stacks Laravel complets** | Support des 4 stacks natifs (`web`, `api`, `console`, `channels`) + possibilité d'en ajouter. |
| **Cache opt-in, opérations idempotentes** | `routify:cache` met en cache la liste des fichiers, `routify:clear` invalide. Identique au flow `route:cache` natif. |
| **Exceptions explicites** | Une racine introuvable ou un pattern invalide lève une `RoutifyException`, jamais de silence ni de try/catch vide. |
| **Typage strict & immuabilité** | `declare(strict_types=1)` partout, value objects `final readonly`, classes `final` quand non destinées à l'extension. |
| **Test-driven** | Les couches `Discovery/` et `Support/` sont écrites en TDD avec Pest 3 + Testbench. |

### Naming
- **Vendor** : `kirago`
- **Package** : `laravel-routify`
- **Nom complet** : `kirago/laravel-routify`
- **Namespace racine** : `Kirago\Routify`
- **Facade** : `Routify` (alias `Kirago\Routify\Facades\Routify`)

---

## 1. API publique cible (design first)

L'agent doit garder cette API en tête à chaque ligne de code écrite. Toute déviation = bug.

### 1.1 Usage minimal
```php
// dans bootstrap/app.php OU dans un ServiceProvider du projet hôte
use Kirago\Routify\Facades\Routify;

Routify::discover();              // tous les stacks activés (web + api + console + channels)
Routify::discoverApi('v1');       // uniquement le stack api avec préfixe override
Routify::discoverWeb();
Routify::discoverConsole();
Routify::discoverChannels();
```

### 1.2 Usage avancé (fluent builder)
```php
Routify::for('api')
    ->in(app_path('Modules'))           // override des paths configurés
    ->withPrefix('api/v1')
    ->withMiddleware(['api', 'throttle:60,1'])
    ->withName('api.')
    ->withDomain('{tenant}.example.com')
    ->matching('api*.php')              // override du pattern glob
    ->load();
```

### 1.3 Configuration (`config/routify.php`)
Chaque champ est commenté pour qu'un nouveau dev comprenne immédiatement à quoi il sert.

```php
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Paths à scanner
    |--------------------------------------------------------------------------
    |
    | Liste des dossiers racines dans lesquels Routify va chercher
    | récursivement les fichiers de routes. Chaque path doit être
    | un chemin absolu. Les paths inexistants lèvent une exception
    | au boot pour éviter les "scans silencieux qui ne trouvent rien".
    |
    | Exemples valables :
    |   app_path('Modules')
    |   app_path('Features')
    |   base_path('packages')
    |
    */
    'paths' => [
        app_path('Modules'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-discovery au boot
    |--------------------------------------------------------------------------
    |
    | Si true, le ServiceProvider appelle automatiquement Routify::discover()
    | au boot — l'utilisateur n'a rien à faire. Si false (recommandé),
    | l'utilisateur doit appeler explicitement Routify::discoverApi()
    | (ou autre) depuis son ServiceProvider, pour garder le contrôle.
    |
    */
    'auto_discover_on_boot' => false,

    /*
    |--------------------------------------------------------------------------
    | Définition des stacks de routes
    |--------------------------------------------------------------------------
    |
    | Un "stack" représente une famille de routes Laravel (web, api,
    | console, channels) ou une famille custom (admin, internal...).
    | Chaque stack définit son pattern glob, ses middlewares,
    | son préfixe d'URL, son préfixe de nom de route et son domaine.
    |
    | Champs par stack :
    |   - enabled    : bool   — active/désactive le stack
    |   - pattern    : string — glob qui matche les fichiers à charger
    |                            (relatif aux paths configurés, scan récursif)
    |   - middleware : array  — groupe(s) de middleware appliqué(s)
    |   - prefix     : ?string — préfixe d'URL (ex: 'api/v1')
    |   - name       : ?string — préfixe de nom de route (ex: 'api.')
    |   - domain     : ?string — restriction par domaine
    |
    | Tu peux ajouter tes propres stacks (ex: 'admin' avec middleware
    | ['web', 'auth', 'admin']) — Routify::for('admin')->load() les chargera.
    |
    */
    'stacks' => [

        'web' => [
            'enabled'    => true,
            'pattern'    => 'web*.php',     // matche web.php, web-public.php, etc.
            'middleware' => ['web'],
            'prefix'     => null,
            'name'       => null,
            'domain'     => null,
        ],

        'api' => [
            'enabled'    => true,
            'pattern'    => 'api*.php',     // matche api.php, api-v1.php, api-internal.php, etc.
            'middleware' => ['api'],
            'prefix'     => 'api',
            'name'       => 'api.',
            'domain'     => null,
        ],

        'console' => [
            'enabled'    => true,
            'pattern'    => 'console*.php', // chargé dans le contexte Artisan
            'middleware' => [],
            'prefix'     => null,
            'name'       => null,
            'domain'     => null,
        ],

        'channels' => [
            'enabled'    => true,
            'pattern'    => 'channels*.php', // chargé dans le contexte Broadcast
            'middleware' => [],
            'prefix'     => null,
            'name'       => null,
            'domain'     => null,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache du résultat de la découverte
    |--------------------------------------------------------------------------
    |
    | En production, scanner le filesystem à chaque boot a un coût.
    | Si activé, la liste des fichiers découverts est mise en cache
    | (via le store cache par défaut) après le premier scan.
    | Utiliser `php artisan routify:cache` pour forcer la mise en cache,
    | et `php artisan routify:clear` pour l'invalider après un déploiement.
    |
    */
    'cache' => [
        'enabled' => env('ROUTIFY_CACHE', false),
        'key'     => 'routify:files',
        'store'   => null, // null = store par défaut (config/cache.php → 'default')
    ],
];
```

> **Note design** : il n'y a **pas** de clé `routes_directory`. La sélection des fichiers passe entièrement par le `pattern` glob du stack, qui est plus expressif et plus flexible (l'utilisateur peut écrire `Routes/api*.php` s'il veut imposer un sous-dossier, ou `**/*.php` pour scanner large).

### 1.4 Commandes Artisan
```bash
php artisan routify:list             # liste les fichiers route découverts par stack (debug)
php artisan routify:cache            # met en cache la liste des fichiers
php artisan routify:clear            # invalide le cache
```

### 1.5 Contrats clés (interfaces)
```php
namespace Kirago\Routify\Contracts;

interface RouteDiscoverer
{
    /**
     * Retourne la liste des fichiers .php matchant `$pattern` sous `$basePath` (récursif).
     *
     * @return list<string> chemins absolus, triés alphabétiquement (déterminisme).
     */
    public function discover(string $basePath, string $pattern): array;
}
```

---

## 2. Structure cible du package

```
kirago/laravel-routify/
├── .github/
│   └── workflows/
│       ├── tests.yml
│       └── pint.yml
├── .gitignore
├── .gitattributes
├── CHANGELOG.md
├── LICENSE.md                          # MIT
├── README.md
├── composer.json
├── phpunit.xml
├── pint.json
├── config/
│   └── routify.php
├── src/
│   ├── Commands/
│   │   ├── CacheCommand.php           # routify:cache
│   │   ├── ClearCommand.php           # routify:clear
│   │   └── ListCommand.php            # routify:list
│   ├── Contracts/
│   │   └── RouteDiscoverer.php
│   ├── Discovery/
│   │   ├── FilesystemRouteDiscoverer.php
│   │   └── CachedRouteDiscoverer.php  # decorator
│   ├── Exceptions/
│   │   ├── RoutifyException.php
│   │   └── InvalidConfigurationException.php
│   ├── Facades/
│   │   └── Routify.php
│   ├── Support/
│   │   ├── StackConfig.php            # value object typed (final readonly)
│   │   └── RouteStackBuilder.php      # fluent builder
│   ├── RoutifyManager.php             # orchestrateur principal
│   └── RoutifyServiceProvider.php
└── tests/
    ├── Pest.php
    ├── TestCase.php                   # extends Orchestra\Testbench\TestCase
    ├── Feature/
    │   ├── DiscoveryTest.php
    │   ├── FacadeTest.php
    │   ├── FluentBuilderTest.php
    │   ├── CacheTest.php
    │   └── CommandsTest.php
    ├── Unit/
    │   ├── FilesystemRouteDiscovererTest.php
    │   └── StackConfigTest.php
    └── fixtures/
        └── modules/
            ├── ModuleA/Routes/api.php
            ├── ModuleA/Routes/web.php
            ├── ModuleB/Routes/api.php
            ├── ModuleB/Routes/api-v2.php
            ├── ModuleC/Routes/sub-folder/api-extra.php
            └── ModuleD/Misc/orphan.php   # ne doit PAS être chargé (hors pattern)
```

---

## 3. Phases d'implémentation

> Chaque phase = commit atomique. Lance les tests après chaque phase. Ne passe à la suivante que si tous les tests passent et `vendor/bin/pint --test` est clean.

### Phase 1 — Initialisation du repo & scaffolding

**Objectif** : repo clonable, `composer install` qui passe, structure de dossiers vide en place.

#### Tâches
- [ ] **1.1** Cloner le repo distant : `git clone git@github.com:kirago-tech/laravel-routify.git` dans le dossier de travail (l'utilisateur t'indiquera le chemin local).
- [ ] **1.2** Vérifier que la branche par défaut est `main` (sinon `git checkout -b main`).
- [ ] **1.3** Créer `composer.json` (voir contenu §3.1.A).
- [ ] **1.4** Créer `.gitignore` (`/vendor`, `/.idea`, `/.phpunit.cache`, `composer.lock`, `.phpunit.result.cache`, `.phpstan.cache`).
- [ ] **1.5** Créer `.gitattributes` (export-ignore pour `tests/`, `.github/`, `phpunit.xml`, `pint.json`, `CHANGELOG.md`).
- [ ] **1.6** Créer `LICENSE.md` (MIT, year 2026, "Simo Joel <joelsimooverride@gmail.com>").
- [ ] **1.7** Créer `pint.json` (voir §3.1.B).
- [ ] **1.8** Créer `phpunit.xml` (voir §3.1.C).
- [ ] **1.9** Créer la structure de dossiers vide (`src/`, `config/`, `tests/Feature`, `tests/Unit`, `tests/fixtures`, etc.).
- [ ] **1.10** `composer install`.
- [ ] **1.11** Premier commit : `chore: initial scaffolding`.

#### 3.1.A — Contenu `composer.json`
```json
{
    "name": "kirago/laravel-routify",
    "description": "Auto-discover and register Laravel route files spread across multiple folders, with configurable glob patterns, middleware groups and a fluent builder.",
    "keywords": ["laravel", "routes", "auto-discovery", "route-discovery", "modules", "kirago"],
    "homepage": "https://github.com/kirago-tech/laravel-routify",
    "license": "MIT",
    "type": "library",
    "authors": [
        { "name": "Simo Joel", "email": "joelsimooverride@gmail.com", "role": "Developer" }
    ],
    "require": {
        "php": "^8.2",
        "illuminate/contracts": "^11.0 || ^12.0",
        "illuminate/routing": "^11.0 || ^12.0",
        "illuminate/support": "^11.0 || ^12.0",
        "illuminate/console": "^11.0 || ^12.0",
        "symfony/finder": "^7.0"
    },
    "require-dev": {
        "orchestra/testbench": "^9.0 || ^10.0",
        "pestphp/pest": "^3.0",
        "pestphp/pest-plugin-laravel": "^3.0",
        "laravel/pint": "^1.18",
        "phpstan/phpstan": "^1.12"
    },
    "autoload": {
        "psr-4": {
            "Kirago\\Routify\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Kirago\\Routify\\Tests\\": "tests/"
        }
    },
    "scripts": {
        "test": "vendor/bin/pest",
        "test:coverage": "vendor/bin/pest --coverage",
        "format": "vendor/bin/pint",
        "format:test": "vendor/bin/pint --test",
        "analyse": "vendor/bin/phpstan analyse"
    },
    "config": {
        "sort-packages": true,
        "allow-plugins": {
            "pestphp/pest-plugin": true
        }
    },
    "extra": {
        "laravel": {
            "providers": [
                "Kirago\\Routify\\RoutifyServiceProvider"
            ],
            "aliases": {
                "Routify": "Kirago\\Routify\\Facades\\Routify"
            }
        }
    },
    "minimum-stability": "stable",
    "prefer-stable": true
}
```

#### 3.1.B — Contenu `pint.json`
```json
{
    "preset": "laravel",
    "rules": {
        "declare_strict_types": true,
        "strict_param": true,
        "ordered_imports": { "sort_algorithm": "alpha" }
    }
}
```

#### 3.1.C — Contenu `phpunit.xml`
```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true"
         cacheDirectory=".phpunit.cache">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory>tests/Feature</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory>src</directory>
        </include>
    </source>
</phpunit>
```

#### Critères de validation phase 1
- ✅ `composer install` passe sans erreur.
- ✅ `vendor/bin/pint --test` ne signale rien sur les fichiers présents.

---

### Phase 2 — Couche de découverte (Discovery layer)

**Objectif** : isoler la logique « scanner le filesystem et trouver les fichiers de route ». Seule dépendance Laravel : `Illuminate\Contracts\Cache\Repository` (uniquement pour le decorator cache).

#### Tâches
- [ ] **2.1** Créer `src/Contracts/RouteDiscoverer.php` (voir §1.5).
- [ ] **2.2** Créer `src/Exceptions/RoutifyException.php` (extends `\RuntimeException`).
- [ ] **2.3** Créer `src/Exceptions/InvalidConfigurationException.php` (extends `RoutifyException`).
- [ ] **2.4** Créer `src/Discovery/FilesystemRouteDiscoverer.php` :
  - Implémente `RouteDiscoverer`.
  - Utilise `Symfony\Component\Finder\Finder` (lisible, testable, glob natif).
  - Le `pattern` reçu est passé tel quel à `Finder::name($pattern)` — Symfony Finder gère les glob patterns (`api*.php`, `*.php`, etc.).
  - Lève `RoutifyException` si `$basePath` n'existe pas (pas de try/catch silencieux).
  - Retourne une `list<string>` triée alphabétiquement (déterminisme : facilite tests + cache key stable).
- [ ] **2.5** Créer `src/Discovery/CachedRouteDiscoverer.php` :
  - Decorator pattern, prend un `RouteDiscoverer` + `Illuminate\Contracts\Cache\Repository` + `string $keyPrefix`.
  - Clé de cache dérivée de `(basePath, pattern)` pour éviter les collisions entre stacks.
  - Utilise `rememberForever()` (invalidation manuelle via `routify:clear`).

#### Tests phase 2 (à écrire AVANT l'implémentation — TDD)
- [ ] `tests/Unit/FilesystemRouteDiscovererTest.php` :
  - Découvre un fichier à la racine du basePath.
  - Découvre un fichier dans un sous-dossier (récursif).
  - Respecte le pattern glob : `api*.php` matche `api.php`, `api-v2.php`, mais **pas** `web.php` ni `console.php`.
  - Pattern `*.php` charge tout (test du cas large).
  - Retourne tableau trié alphabétiquement.
  - Lève `RoutifyException` si `basePath` inexistant.
  - Retourne `[]` si aucun fichier ne matche.

#### Critères de validation phase 2
- ✅ Couverture > 90% sur `Discovery/`.
- ✅ Tous les tests Unit passent.

---

### Phase 3 — Value objects & fluent builder

**Objectif** : éliminer les array-shapes magiques, tout typer.

#### Tâches
- [ ] **3.1** Créer `src/Support/StackConfig.php` :
  - Class `final readonly`.
  - Constructor property promotion.
  - Propriétés : `string $name`, `bool $enabled`, `string $pattern`, `array $middleware`, `?string $prefix`, `?string $namePrefix`, `?string $domain`.
  - Méthode statique `fromArray(string $name, array $config): self` (validation + défauts).
  - Méthodes `with*` immutables : `withPrefix(?string)`, `withMiddleware(array)`, `withName(?string)`, `withDomain(?string)`, `withPattern(string)` retournant un nouveau `StackConfig` (pattern wither).
  - Lève `InvalidConfigurationException` si `pattern` manquant ou non-string.
- [ ] **3.2** Créer `src/Support/RouteStackBuilder.php` :
  - Fluent builder retournant `$this`.
  - Méthodes : `in(string $path)`, `withPrefix(?string)`, `withMiddleware(array|string)`, `withName(?string)`, `withDomain(?string)`, `matching(string)`.
  - Méthode terminale `load(): void` qui appelle `RoutifyManager::loadStackForPaths()`.

#### Tests phase 3
- [ ] `tests/Unit/StackConfigTest.php` :
  - `fromArray()` avec config minimale applique les défauts.
  - `fromArray()` avec config complète garde les valeurs.
  - `fromArray()` avec `pattern` manquant lève `InvalidConfigurationException`.
  - `withPrefix()` retourne une **nouvelle** instance (immutabilité).
- [ ] `tests/Feature/FluentBuilderTest.php` :
  - Le builder enregistre bien les routes (vérifier via `Route::getRoutes()`).

---

### Phase 4 — Manager principal

**Objectif** : orchestration. Classe principale exposée via la facade.

#### Tâches
- [ ] **4.1** Créer `src/RoutifyManager.php` :
  ```php
  declare(strict_types=1);

  namespace Kirago\Routify;

  use Illuminate\Contracts\Config\Repository as Config;
  use Illuminate\Contracts\Routing\Registrar as Router;
  use Kirago\Routify\Contracts\RouteDiscoverer;
  use Kirago\Routify\Support\RouteStackBuilder;
  use Kirago\Routify\Support\StackConfig;

  final class RoutifyManager
  {
      public function __construct(
          private readonly RouteDiscoverer $discoverer,
          private readonly Router $router,
          private readonly Config $config,
      ) {}

      public function discover(): void
      {
          foreach ($this->stacks() as $stack) {
              if ($stack->enabled) {
                  $this->loadStack($stack);
              }
          }
      }

      public function discoverApi(?string $prefix = null): void
      {
          $stack = $this->stack('api');
          $this->loadStack($prefix !== null ? $stack->withPrefix($prefix) : $stack);
      }

      public function discoverWeb(?string $prefix = null): void { /* idem 'web' */ }
      public function discoverConsole(): void { /* idem 'console' */ }
      public function discoverChannels(): void { /* idem 'channels' */ }

      public function for(string $stackName): RouteStackBuilder
      {
          return new RouteStackBuilder($this, $this->stack($stackName));
      }

      /** @internal Appelé par le builder ET par discover*() */
      public function loadStack(StackConfig $stack, ?array $pathsOverride = null): void
      {
          $files = [];
          foreach ($pathsOverride ?? $this->paths() as $basePath) {
              foreach ($this->discoverer->discover($basePath, $stack->pattern) as $file) {
                  $files[$file] = true; // dédup
              }
          }

          foreach (array_keys($files) as $file) {
              $this->registerRouteFile($stack, $file);
          }
      }

      private function registerRouteFile(StackConfig $stack, string $file): void
      {
          // 'console' / 'channels' n'utilisent pas Route::group()
          // → require direct dans leur contexte (cf. Phase 4.2)
          $group = $this->router;
          if ($stack->middleware)  $group = $group->middleware($stack->middleware);
          if ($stack->prefix)      $group = $group->prefix($stack->prefix);
          if ($stack->namePrefix)  $group = $group->name($stack->namePrefix);
          if ($stack->domain)      $group = $group->domain($stack->domain);
          $group->group($file);
      }

      // helpers privés : stacks(): array<StackConfig>, stack(string): StackConfig,
      //                  paths(): array<string>
  }
  ```
- [ ] **4.2** **Edge cases** :
  - **Déduplication** : si deux paths se chevauchent, le même fichier ne doit être chargé qu'une fois.
  - **`console` stack** : ne passe pas par `Route::group()`. Il faut `require` le fichier dans le contexte Artisan (`Illuminate\Foundation\Console\Kernel::load()` ou équivalent : `require_once` simple dans la console). Implémenter une stratégie spécifique au stack `console` qui détecte le nom et bypass le router.
  - **`channels` stack** : idem, mais pour `Broadcast::channel()`. `require` direct dans le contexte Broadcast.
  - **Path inexistant** : si un path configuré n'existe pas, lever `RoutifyException` avec un message clair plutôt que de scanner silencieusement rien.
- [ ] **4.3** Créer `src/Facades/Routify.php` (avec docblock `@method static` complet pour chaque méthode publique).

#### Tests phase 4
- [ ] `tests/Feature/DiscoveryTest.php` :
  - `Routify::discoverApi()` enregistre toutes les routes des fixtures matchant `api*.php`.
  - Préfixe `api/v1` appliqué quand passé en argument.
  - Middleware `api` appliqué (vérifier via `Route::getRoutes()->getRoutes()[0]->middleware()`).
  - `discoverWeb()` ignore les fichiers `api*.php`.
  - Un même fichier dans deux paths qui se chevauchent n'est chargé qu'une fois.
- [ ] `tests/Feature/FacadeTest.php` :
  - La facade résout bien `RoutifyManager` depuis le container.

---

### Phase 5 — ServiceProvider

**Objectif** : binding container, publish config, auto-discovery au boot si activée.

#### Tâches
- [ ] **5.1** Créer `src/RoutifyServiceProvider.php` :
  ```php
  public function register(): void
  {
      $this->mergeConfigFrom(__DIR__.'/../config/routify.php', 'routify');

      $this->app->singleton(RouteDiscoverer::class, function ($app) {
          $discoverer = new FilesystemRouteDiscoverer();

          if ($app['config']->get('routify.cache.enabled')) {
              $store = $app['config']->get('routify.cache.store');
              $cache = $app->make('cache')->store($store);
              $discoverer = new CachedRouteDiscoverer(
                  $discoverer,
                  $cache,
                  $app['config']->get('routify.cache.key'),
              );
          }
          return $discoverer;
      });

      $this->app->singleton(RoutifyManager::class);
  }

  public function boot(): void
  {
      if ($this->app->runningInConsole()) {
          $this->publishes([
              __DIR__.'/../config/routify.php' => config_path('routify.php'),
          ], 'routify-config');

          $this->commands([
              ListCommand::class,
              CacheCommand::class,
              ClearCommand::class,
          ]);
      }

      if ($this->app['config']->get('routify.auto_discover_on_boot')) {
          $this->app->make(RoutifyManager::class)->discover();
      }
  }
  ```
- [ ] **5.2** Créer `config/routify.php` (voir §1.3).

#### Critères phase 5
- ✅ Dans une app Testbench, `app(RoutifyManager::class)` retourne bien l'instance.
- ✅ `config('routify')` retourne le tableau attendu.
- ✅ Le cache est bien décoré quand `routify.cache.enabled = true`.

---

### Phase 6 — Commandes Artisan

**Objectif** : observabilité et gestion du cache.

#### Tâches
- [ ] **6.1** Créer `src/Commands/ListCommand.php` (`routify:list`) :
  - Affiche un tableau : `Stack | Path | File | Pattern matched | Middleware | Prefix`.
  - Utilise `$this->table()` de la classe Command.
  - Option `--stack=api` pour filtrer.
- [ ] **6.2** Créer `src/Commands/CacheCommand.php` (`routify:cache`) :
  - Force le scan de tous les paths/stacks et écrit la liste en cache.
  - Affiche un résumé (nombre de fichiers par stack).
- [ ] **6.3** Créer `src/Commands/ClearCommand.php` (`routify:clear`) :
  - Forget la clé de cache.
  - Affiche un message de confirmation.

#### Tests phase 6
- [ ] `tests/Feature/CommandsTest.php` :
  - `artisan('routify:list')` affiche les fixtures.
  - `artisan('routify:cache')` puis `cache->has(...)` est true.
  - `artisan('routify:clear')` puis `cache->has(...)` est false.

---

### Phase 7 — Tests d'intégration & fixtures

**Objectif** : valider end-to-end avec une vraie app Laravel via Testbench.

#### Tâches
- [ ] **7.1** Créer `tests/TestCase.php` extends `Orchestra\Testbench\TestCase` :
  - Override `getPackageProviders()` → `[RoutifyServiceProvider::class]`.
  - Override `getEnvironmentSetUp()` → set `routify.paths` vers `__DIR__.'/fixtures/modules'`.
- [ ] **7.2** Créer `tests/Pest.php` (configurer `uses(TestCase::class)->in('Feature', 'Unit')`).
- [ ] **7.3** Créer les fixtures (chaque fichier déclare 1 route triviale pour pouvoir l'asserter) :
  - `tests/fixtures/modules/ModuleA/Routes/api.php` → `Route::get('/users', fn () => 'a-users');`
  - `tests/fixtures/modules/ModuleA/Routes/web.php` → `Route::get('/dashboard', fn () => 'dash');`
  - `tests/fixtures/modules/ModuleB/Routes/api.php` → `Route::post('/orders', fn () => 'orders');`
  - `tests/fixtures/modules/ModuleB/Routes/api-v2.php` → `Route::get('/v2/orders', fn () => 'v2');`
  - `tests/fixtures/modules/ModuleC/Routes/sub-folder/api-extra.php` → `Route::get('/extra', fn () => 'extra');` (test récursivité)
  - `tests/fixtures/modules/ModuleD/Misc/orphan.php` → `Route::get('/orphan', fn () => 'orphan');` (ne doit **pas** être chargé avec le pattern `api*.php`)
- [ ] **7.4** Créer `tests/Feature/CacheTest.php` :
  - Premier appel : `RouteDiscoverer` hit le filesystem.
  - Deuxième appel : valeur depuis cache (mock du discoverer pour vérifier 1 seul call).
  - `routify:clear` puis nouvel appel → re-hit filesystem.

#### Critères phase 7
- ✅ `vendor/bin/pest` → 100% green.
- ✅ Couverture globale > 85%.

---

### Phase 8 — Documentation

**Objectif** : un dev externe doit pouvoir installer et utiliser sans poser de question.

#### Tâches
- [ ] **8.1** Créer `README.md` avec sections :
  1. Badges (Packagist version, downloads, tests, license).
  2. Why this package (en 3 lignes — le problème, la solution, ce qui le différencie).
  3. Installation (`composer require kirago/laravel-routify`).
  4. Quick start (1 exemple minimal copy-paste).
  5. Configuration (publish + sections principales).
  6. Usage avancé (fluent builder, multi-paths, custom stacks).
  7. Artisan commands.
  8. Testing (`composer test`).
  9. Changelog & Contributing & License.
- [ ] **8.2** Créer `CHANGELOG.md` (format Keep a Changelog) avec entrée `1.0.0`.

---

### Phase 9 — CI GitHub Actions

#### Tâches
- [ ] **9.1** Créer `.github/workflows/tests.yml` :
  - Matrice : PHP `8.2`, `8.3`, `8.4` × Laravel `11.*`, `12.*` × `prefer-lowest`, `prefer-stable`.
  - Steps : checkout, setup-php, composer install, run pest.
- [ ] **9.2** Créer `.github/workflows/pint.yml` :
  - Trigger sur push/PR.
  - `vendor/bin/pint --test`.

---

## 4. Règles non négociables (rappels pour l'agent)

### Code
- ❌ Pas de `try/catch` vide. Une erreur de découverte = exception remontée.
- ❌ Pas de `RecursiveDirectoryIterator` brut. Utiliser `Symfony\Component\Finder` (testable, lisible).
- ❌ Pas d'array-shape sans typage. Tous les payloads passent par `StackConfig`.
- ❌ Pas de path codé en dur (ex: `app/Modules`) dans `src/`. Tout passe par la config.
- ✅ `declare(strict_types=1);` en tête de chaque fichier `.php`.
- ✅ Type hints + return types partout.
- ✅ Properties readonly + constructor promotion (PHP 8.2+).
- ✅ Tests d'abord (TDD) sur les couches `Discovery/` et `Support/`.
- ✅ `final` sur les classes qui ne sont pas pensées pour être étendues (Manager, Discoverer, ValueObjects).

### Git
- 1 commit par phase, message conventionnel : `feat(discovery): add filesystem discoverer`, `test(discovery): add fixtures`, etc.
- Ne jamais push avant validation utilisateur.

### Tests
- Pest 3, pas PHPUnit pur.
- Couverture cible : 90% minimum sur `Discovery/`, `Support/`, `RoutifyManager`.
- Tests `Feature` via Testbench, tests `Unit` purs (sans Laravel boot).

### Sortie de l'agent à chaque phase
À la fin de chaque phase, l'agent doit afficher :
1. Liste des fichiers créés/modifiés (chemins absolus).
2. Output de `vendor/bin/pest` (résumé).
3. Output de `vendor/bin/pint --test` (clean ou diff).
4. Confirmation que les critères de validation de la phase sont remplis.

---

## 5. Décisions à arbitrer avant de commencer

L'agent doit poser ces questions à l'utilisateur **avant Phase 1**, regroupées en un seul message :

1. **Chemin local du clone** : où veux-tu que le repo `kirago-tech/laravel-routify` soit cloné sur ton disque ?
2. **Branche par défaut** : `main` ou `master` ?
3. **Auto-discover on boot** (config `auto_discover_on_boot`) : `false` par défaut (opt-in explicite, plus safe → recommandé) ou `true` (zero-config, plus magique) ?
4. **Support `console.php` / `channels.php`** dès la 1.0, ou roadmap 1.1 (pour réduire le scope du MVP) ?
5. **Stacks par défaut** : ne livrer que `web` + `api`, ou les 4 stacks Laravel (`web`, `api`, `console`, `channels`) ?

---

## 6. Roadmap post-1.0 (hors scope de ce plan)

À garder en tête mais **ne pas implémenter** maintenant :
- 1.1 : support `Route::scopeBindings()` configurable par stack.
- 1.2 : génération automatique de la doc OpenAPI à partir des routes découvertes.
- 1.3 : intégration `pestphp/pest-plugin-laravel` pour des assertions custom (`expect()->toHaveDiscoveredRoute('api.users.index')`).
- 1.4 : support multi-tenancy (paths dynamiques par tenant).
- 1.5 : intégration `php artisan route:cache` natif (réutiliser le mécanisme de Laravel plutôt qu'un cache custom).

---

*Plan à exécuter séquentiellement par un agent Claude Code dans un repo neuf. Toute déviation du plan doit être justifiée et validée par l'utilisateur.*