# ADR-0011 — Trois commandes Artisan : `list`, `cache`, `clear`

**Statut** : Accepté
**Date** : 2026-04-25

## Contexte

Une fois la découverte automatique en place, deux besoins
opérationnels apparaissent côté équipe :

1. **Observabilité** — *"qu'est-ce qui est chargé exactement ? quel
   fichier vient de quel module ?"* Sans réponse, le débogage d'une
   route absente devient un jeu d'adresse aveugle.
2. **Gestion du cache** — `route:cache` natif de Laravel sérialise les
   routes ; le cache filesystem de Routify, lui, mémoïse la **liste
   des fichiers** entre deux scans. Il faut pouvoir le pré-chauffer
   (déploiement) et l'invalider (rollback, modification de config).

## Décision

Trois commandes, scopées sous le préfixe `routify:` :

### `routify:list [--stack=name]`

Affiche un tableau `Stack | Path | File | Pattern | Middleware | Prefix`
construit en demandant à `RouteDiscoverer` de scanner chaque path pour
chaque stack actif. Le flag `--stack=admin` restreint à un seul stack
pour réduire le bruit.

```
+-------+-----------------------+----------------------------+----------+------------+--------+
| Stack | Path                  | File                       | Pattern  | Middleware | Prefix |
+-------+-----------------------+----------------------------+----------+------------+--------+
| api   | /app/Modules          | Billing/Routes/api.php     | api*.php | api        | api    |
| api   | /app/Modules          | Catalog/Routes/api-v2.php  | api*.php | api        | api    |
| web   | /app/Modules          | Billing/Routes/web.php     | web*.php | web        | -      |
+-------+-----------------------+----------------------------+----------+------------+--------+
```

### `routify:cache`

Pour chaque stack actif × chaque path, appelle
`$discoverer->discover($path, $stack->pattern)`. Quand le SP a wrappé
le discoverer en `CachedRouteDiscoverer` (cas
`routify.cache.enabled = true`), chaque appel populate le store. Quand
le cache est désactivé, la commande **refuse** de tourner avec un
message clair plutôt que de scanner pour rien :

> *Routify cache is disabled. Set ROUTIFY_CACHE=true (or
> routify.cache.enabled = true) and retry.*

### `routify:clear`

Recalcule la même clé de cache (via
`CachedRouteDiscoverer::cacheKey()`) pour chaque couple
(path, stack), et appelle `$store->forget($key)`. La commande compte
les clés réellement oubliées et le rapporte. Quand le cache est
désactivé, comportement no-op avec message informatif :

> *Routify cache is disabled — nothing to clear.*

### Enregistrement dans le service provider

Conditionnel sur `runningInConsole()` pour éviter le coût (minime mais
réel) en contexte HTTP :

```php
public function boot(): void
{
    if ($this->app->runningInConsole()) {
        $this->publishes([…], 'routify-config');

        $this->commands([
            ListCommand::class,
            CacheCommand::class,
            ClearCommand::class,
        ]);
    }
    // …
}
```

## Alternatives envisagées

- **Une seule commande `routify` avec sous-commandes
  (`routify list`, `routify cache`)** — rejeté : Laravel/Symfony
  console ne supporte pas naturellement les sous-commandes ; les
  commandes scopées par `:` sont la convention Laravel
  (`route:cache`, `cache:clear`, `migrate:fresh`).
- **Pas de commandes séparées, tout dans `Routify::cache()` /
  `Routify::clear()` programmatique** — rejeté : retire le confort
  CLI (déploiement scripts, opérations) et l'observabilité instantanée.

## Conséquences

- ✅ Trois opérations distinctes, chacune a un seul responsibility.
- ✅ Convention de nommage cohérente avec Laravel
  (`route:cache` / `route:clear` / `route:list`).
- ✅ Refus explicite quand le cache est désactivé : pas de
  fausse-réussite trompeuse.
- ⚠️ Les commandes lisent la config et instancient leurs propres
  `StackConfig` plutôt que de passer par `RoutifyManager`. Légère
  duplication de logique — assumée pour garder les commandes
  dépendant uniquement du contract `RouteDiscoverer` et pas de toute
  l'API du manager.