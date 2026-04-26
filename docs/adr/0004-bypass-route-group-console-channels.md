# ADR-0004 — Console et channels : bypass de `Route::group()` via un champ `context`

**Statut** : Accepté
**Date** : 2026-04-26

## Contexte

Pour un stack HTTP (`web`, `api`, ou un stack custom comme `admin`), le
manager enregistre les fichiers via :

```php
Route::middleware($middleware)
    ->prefix($prefix)
    ->name($namePrefix)
    ->domain($domain)
    ->group($file);
```

`Route::group($file, …)` `require`-d le fichier dans le contexte du
routeur HTTP, en lui appliquant les attributs du group (middleware,
prefix, etc.).

Mais Laravel a deux familles de routes qui ne passent **pas** par le
routeur HTTP :

- **Console** — les commandes Artisan se déclarent via
  `Artisan::command(...)`. Elles sont enregistrées dans un kernel
  Artisan, pas dans le `RouteCollection`.
- **Channels** — les channels Broadcast se déclarent via
  `Broadcast::channel(...)`. Elles vivent dans le `BroadcastManager`.

Si on `Route::group()` un fichier `console.php`, les
`Artisan::command(...)` à l'intérieur sont enregistrés correctement
(la facade `Artisan` est globale), mais on hérite de middleware HTTP
non pertinents et d'un préfixe URL inutile attachés au group. Il faut
donc un mécanisme de bypass pour ces familles, **et** un moyen de
discriminer "stack HTTP" vs "stack non-HTTP".

## Décision

Le discriminant est un **champ explicite `context` sur `StackConfig`**,
typé via trois constantes :

```php
final readonly class StackConfig
{
    public const CONTEXT_HTTP = 'http';
    public const CONTEXT_CLI = 'cli';
    public const CONTEXT_BROADCAST = 'broadcast';
    // …

    public function __construct(
        // …
        public string $context = self::CONTEXT_HTTP,
    ) {}
}
```

`StackConfig::fromArray()` résout le `context` avec un défaut "smart"
qui préserve la compatibilité ascendante :

```php
return match ($name) {
    'console'  => self::CONTEXT_CLI,
    'channels' => self::CONTEXT_BROADCAST,
    default    => self::CONTEXT_HTTP,
};
```

L'utilisateur peut **explicitement** définir le context d'un stack
custom :

```php
'webhooks' => [
    'pattern' => 'webhook*.php',
    'context' => 'cli',  // require_once dans le contexte global
],
```

Le manager dispatche sur ce champ :

```php
private function registerRouteFile(StackConfig $stack, string $file): bool
{
    if ($stack->context !== StackConfig::CONTEXT_HTTP) {
        require_once $file;

        return false;
    }

    // … chaîne Route::group() pour les stacks HTTP
}
```

`require_once` (et non `require`) garantit qu'un même fichier n'est
pas chargé deux fois, défense en profondeur cohérente avec la dédup
multi-paths côté HTTP.

## Alternatives envisagées

- **Détection par nom de stack hardcodé** (`$stack->name === 'console' || $stack->name === 'channels'`) —
  approche initiale, rejetée à la deuxième passe : fragile (un stack
  custom légitimement nommé `console` se voit appliquer le bypass sans
  qu'on l'ait demandé), pas extensible (un 5e contexte Laravel
  imposerait un `||` supplémentaire), et illisible (la décision dépend
  d'un littéral string disséminé dans le code).
- **Deux discoverers distincts (HTTP vs non-HTTP)** — rejeté :
  l'algorithme de scan est identique, seul le mode d'enregistrement
  change.
- **Flag booléen `bypass_router`** — rejeté : trois contextes ne se
  modélisent pas en un booléen, et un nom positif (`context = "cli"`)
  documente mieux l'intention que la négation (`bypass_router = true`).

## Conséquences

- ✅ Comportement Laravel-natif respecté pour les commandes et channels.
- ✅ La même config stack (paths + pattern) fonctionne uniformément
  pour les 4 familles de routes.
- ✅ Un stack custom peut explicitement opter pour le contexte CLI ou
  broadcast (`'context' => 'cli'`), sans dépendre de son nom.
- ✅ La validation est centralisée dans `StackConfig::fromArray` :
  un `context` inconnu lève `InvalidConfigurationException` au boot.
- ✅ Backward compatible : les configs existantes qui n'ont pas le
  champ `context` voient le bon contexte résolu via le `match` sur
  le nom — `console` reste CLI, `channels` reste broadcast.
- ⚠️ Les attributs `prefix` / `middleware` / `name` / `domain` sont
  **ignorés** quand `context !== 'http'`. Cohérent (ils n'ont pas de
  sens hors HTTP) mais inattendu pour qui ne lirait pas le code.