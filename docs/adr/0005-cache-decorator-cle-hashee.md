# ADR-0005 — Cache opt-in via decorator et clé hashée par couple `(basePath, pattern)`

**Statut** : Accepté
**Date** : 2026-04-25

## Contexte

Scanner le filesystem coûte des millisecondes par stack à chaque boot.
Multiplié par le nombre de stacks (jusqu'à 4 par défaut, davantage avec
des stacks customs) et par le nombre de paths (multi-modules), ça finit
par être visible dans le P99 de boot d'une app Laravel en production.

Trois manières d'introduire un cache :

1. **Cache intégré au discoverer principal** — la classe filesystem
   gère elle-même son cache. Le couple "scan" + "cache" devient
   indissociable, et tester le scan sans cache nécessite des
   conditionnelles dans le code.
2. **Decorator** — `CachedRouteDiscoverer` enveloppe
   `FilesystemRouteDiscoverer` et est branché conditionnellement.
3. **Cache au niveau du `RoutifyManager`** — fuit la responsabilité
   hors de la couche Discovery, et nécessite que le manager connaisse
   les détails d'implémentation du discoverer.

## Décision

### Decorator pattern

`CachedRouteDiscoverer implements RouteDiscoverer` prend en
constructeur :

- un `RouteDiscoverer` (typiquement le filesystem)
- un `Illuminate\Contracts\Cache\Repository`
- un `string $keyPrefix` (depuis `routify.cache.key`)

Sur `discover($basePath, $pattern)`, il appelle :

```php
return $this->cache->rememberForever(
    self::cacheKey($this->keyPrefix, $basePath, $pattern),
    fn (): array => $this->inner->discover($basePath, $pattern),
);
```

Le service provider branche le decorator **conditionnellement** :

```php
if ($config->get('routify.cache.enabled')) {
    $discoverer = new CachedRouteDiscoverer($filesystem, $cache, $keyPrefix);
}
```

Quand `routify.cache.enabled = false`, le filesystem discoverer est
exposé directement sans wrapper — zéro coût, zéro indirection.

### Clé hashée par couple `(basePath, pattern)`

```php
public static function cacheKey(string $keyPrefix, string $basePath, string $pattern): string
{
    return $keyPrefix.':'.hash('xxh128', $basePath.'|'.$pattern);
}
```

Une clé par couple **(basePath, pattern)** — pas une clé globale — pour
deux raisons :

1. Si deux stacks (ex: `api*.php` et `web*.php`) cherchent dans le même
   `basePath`, ils produisent des résultats différents qu'il ne faut
   pas écraser mutuellement.
2. `routify:clear` peut recalculer la même clé à partir de la config et
   l'invalider précisément, sans toucher aux autres entrées du cache
   store partagé avec le reste de l'app.

La méthode `cacheKey()` est exposée en `public static` pour que la
commande `routify:clear` la réutilise sans dupliquer la logique de hash.

## Alternatives envisagées

- **Cache intégré au discoverer** — rejeté : empêche de tester le scan
  filesystem sans monkey-patcher un faux cache.
- **Cache global avec un manifeste (un seul `routify:files` qui contient
  toute la map)** — rejeté : invalidation tout-ou-rien, pas de cache
  partiel possible quand on warm un seul stack via `routify:cache --stack=api`
  (feature future).
- **Cache TTL** — rejeté : invalidation manuelle (`routify:clear` après
  déploiement) suit le modèle Laravel natif (`route:cache` /
  `route:clear`) et est plus prédictible.

## Conséquences

- ✅ Filesystem discoverer testable en pur unit test, indépendant du
  cache (10 tests).
- ✅ Cache discoverer testable avec un cache `array` Laravel
  in-memory (3 tests).
- ✅ Clé déterministe et précisément invalidable par
  `routify:clear`.
- ✅ Le cache est désactivé par défaut (`ROUTIFY_CACHE` env), ce qui
  évite les surprises en dev.
- ⚠️ Une chaîne `basePath` non-canonique (`/foo/../bar` vs `/bar`)
  produit une clé différente même pour le même dossier physique. Le
  package ne fait **pas** de `realpath()` automatique pour ne pas
  imposer un coût I/O à chaque hash. Documenté.