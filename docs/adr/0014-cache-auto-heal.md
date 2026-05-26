# ADR-0014 — Cache auto-heal et escape hatch des commandes routify:*

**Statut** : Accepté
**Date** : 2026-05-27

## Contexte

L'ADR-0005 a posé le contrat initial du `CachedRouteDiscoverer` :
`rememberForever` sur une clé hashée par couple `(basePath, pattern)`.
Ce contrat est **opaque** — le cache est traité comme la source de vérité
et n'est jamais validé contre le disque.

Trois défauts cumulés sont apparus en production dès la 1.1 :

1. **Cache stale-forever.** Quand un fichier référencé en cache disparaît
   (renommage 1.0 → 1.1 vers la convention dossier, suppression de module,
   réorganisation de routes), le cache continue de pointer vers l'ancien
   chemin. La prochaine `discover()` retourne donc une liste dangling.
2. **Loader brutal.** `RoutifyManager::loadStack()` itère cette liste et
   passe chaque chemin à `Router::group($file)`, qui fait `require $file`.
   Sur un chemin disparu, PHP lève un `ErrorException: Failed to open
   stream`. L'erreur explose pendant le `boot()` du service provider —
   donc avant qu'aucune commande Artisan ne puisse tourner.
3. **Pas d'issue de secours.** `routify:clear` est *exactement* la
   commande prévue pour réparer ce cas. Mais comme elle ne peut être
   invoquée qu'après que le provider ait booté, et que ce boot crashe,
   l'opérateur se retrouve enfermé. Avec un store cache `file` ou `array`
   un redémarrage suffit ; avec `database` (très courant en prod) le
   cache survit et la situation est bloquante.

Le rapport utilisateur (PR locale dans `kiracare`) a montré ce piège :
mise à jour 1.0 → 1.1, renommage des fichiers de routes vers la convention
folder, `php artisan` impossible à invoquer.

## Décision

Trois garde-fous *indépendants* — chacun corrige le défaut à sa couche
respective, et chacun seul suffit déjà à débloquer le scénario du
rapport. La combinaison rend le piège architecturalement impossible.

### 1. Validation à la lecture (`CachedRouteDiscoverer`)

`rememberForever` est remplacé par un `get` + `is_file()`-par-élément.
Si **au moins un** chemin cache n'existe plus, la clé est `forget()`'ée et
le discoverer interne est consulté à nouveau. Le résultat frais est
réécrit. Coût : `O(n)` `is_file()` par hit, négligeable face au scan
Symfony Finder qu'on évite.

```php
private function rememberValidated(string $key, callable $producer): array
{
    $cached = $this->cache->get($key);
    if (is_array($cached) && self::allExist($cached)) {
        return $cached;
    }
    if ($cached !== null) {
        $this->cache->forget($key);
    }
    $fresh = $producer();
    $this->cache->forever($key, $fresh);
    return $fresh;
}
```

### 2. Loader tolérant (`RoutifyManager::loadStack`)

Avant chaque `registerRouteFile($stack, $file)`, on fait `is_file($file)`.
Si faux, on saute silencieusement. C'est la défense en profondeur : même
si un discoverer custom retourne un chemin invalide, ou si une race
condition supprime un fichier entre le scan et la load, Laravel ne
crashe pas.

### 3. Escape hatch boot (`RoutifyServiceProvider::boot`)

Quand le binaire `artisan` est appelé avec une commande `routify:*`,
`boot()` retourne avant d'appeler `discover()`. Les commandes de
maintenance restent donc atteignables quel que soit l'état du cache, du
disque ou du store backend.

```php
if ($this->app->runningInConsole()
    && self::isMaintenanceCommand((string) ($_SERVER['argv'][1] ?? ''))
) {
    return;
}
```

`isMaintenanceCommand()` est exposée en `public static` pour rester
testable sans bootstrap framework (cf. `tests/Unit/RoutifyServiceProviderTest.php`).

## Alternatives envisagées

- **Versioner le cache par mtime du dossier racine.** Rejeté : fragile
  sur Windows / réseau / FUSE, et ne couvre pas les renommages internes
  qui ne touchent pas la mtime du parent. La validation par fichier est
  plus robuste et plus chère seulement marginalement.
- **TTL court.** Rejeté : pousse le problème dans le temps sans le
  régler — un cache stale 60 secondes plante quand même. La validation à
  la lecture est synchrone avec l'état réel.
- **Logger un warning sur cache stale.** Considéré, non retenu pour
  cette release. Le log à l'invalidation est ajoutable plus tard sans
  changer le contrat. La priorité ici est : ne plus jamais bloquer la
  boot chain.
- **Skipper la discovery uniquement pour `routify:clear` (pas pour
  `routify:cache` / `routify:list` / `routify:optimize`).** Rejeté :
  `routify:cache` peut avoir besoin de re-warm après un changement, et
  doit pouvoir tourner sans qu'une auto-discovery précédente ait crashé.
  La règle uniforme « tout `routify:*` skip » est plus simple et plus
  prévisible.

## Conséquences

- ✅ Aucun renommage / suppression / réorganisation de fichier ne peut
  bloquer le boot Laravel.
- ✅ Les commandes `routify:*` restent **toujours** atteignables, même
  avec `auto_discover_on_boot=true`, cache `database`, et un cache stale.
- ✅ Le cache redevient cohérent avec le disque dès la première lecture
  qui détecte un drift — sans intervention manuelle (`routify:clear`
  reste utile en CI/CD pour pré-empter, mais n'est plus *requis* pour
  réparer le runtime).
- ⚠️ **Changement de contrat** : un test qui pré-seedait le cache avec
  des chemins synthétiques (`/seeded/from/cache.php`) doit maintenant
  pointer vers de vrais fichiers — sinon la validation invalide le seed.
  Documenté dans le CHANGELOG sous *Changed*.
- ⚠️ Coût : `O(n)` `stat()` par cache hit. Mesuré dans la suite Pest :
  imperceptible (< 1 ms sur 50 fichiers locaux). Pour une app à très
  gros fan-out (>1000 fichiers de routes), refaire le bench si la P99
  de boot devient un problème ; une option de désactivation (`routify.cache.validate`)
  pourra être ajoutée en mode opt-out sans changer le défaut.