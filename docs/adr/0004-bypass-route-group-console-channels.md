# ADR-0004 — Console et channels : bypass de `Route::group()`

**Statut** : Accepté
**Date** : 2026-04-25

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
`Artisan::command(...)` à l'intérieur sont quand même enregistrés
correctement (la facade `Artisan` est globale et résout depuis le
container). Mais on hérite de middleware HTTP non pertinents et d'un
préfixe URL inutile attachés au group, tout en faisant passer le
fichier par un détour conceptuellement faux.

## Décision

Détection par nom de stack dans `RoutifyManager::registerRouteFile()` :

```php
private function registerRouteFile(StackConfig $stack, string $file): void
{
    if ($stack->name === 'console' || $stack->name === 'channels') {
        require_once $file;

        return;
    }

    // … chaîne Route::group() pour les stacks HTTP
}
```

`require_once` charge le fichier dans le contexte global, où les
facades `Artisan` et `Broadcast` sont disponibles, sans wrapper HTTP
parasite.

`require_once` (et non `require`) garantit qu'un même fichier n'est pas
chargé deux fois si la déduplication multi-paths est défaillante — défense
en profondeur cohérente avec la dédup faite côté HTTP.

## Alternatives envisagées

- **Route::group() pour tous les stacks** — rejeté : applique des
  middleware HTTP non pertinents aux commandes et channels.
- **Détecter par flag dans `StackConfig`** (ex: `'context' => 'console'`) —
  rejeté : surcomplication. Les noms `console` et `channels` sont déjà
  les noms canoniques Laravel, les utiliser comme clé est lisible.
- **Deux discoverers distincts (HTTP vs non-HTTP)** — rejeté :
  l'algorithme de scan est identique, seul le mode d'enregistrement
  change.

## Conséquences

- ✅ Comportement Laravel-natif respecté pour les commandes et channels.
- ✅ La même config stack (paths + pattern) fonctionne uniformément pour
  les 4 familles de routes.
- ⚠️ Les attributs `prefix` / `middleware` / `name` / `domain` de la
  config stack sont **ignorés** pour `console` et `channels`. C'est
  cohérent — ils n'ont pas de sens hors HTTP — mais inattendu pour qui
  ne lirait pas le code.
- ⚠️ La détection par nom est rigide (`=== 'console'`). Un stack custom
  qui aurait besoin du même bypass devrait s'appeler exactement
  `console` ou `channels`. Cas tordu, jamais rencontré en pratique.