# ADR-0011 — Quatre commandes Artisan : `list`, `cache`, `clear`, `optimize`

**Statut** : Accepté
**Date** : 2026-04-26

## Contexte

Une fois la découverte automatique en place, trois besoins
opérationnels apparaissent côté équipe :

1. **Observabilité** — *"qu'est-ce qui est chargé exactement ? quel
   fichier vient de quel module ?"* Sans réponse, le débogage d'une
   route absente devient un jeu d'adresse aveugle.
2. **Gestion du cache** — `route:cache` natif de Laravel sérialise les
   routes ; le cache filesystem de Routify, lui, mémoïse la **liste
   des fichiers** entre deux scans. Il faut pouvoir le pré-chauffer
   (déploiement) et l'invalider (rollback, modification de config).
3. **Intégration CI/CD** — un pipeline veut une seule commande à
   appeler après `composer install` qui s'occupe de tout (clear puis
   re-warm), sans avoir à connaître l'ordre exact ni à scripter deux
   appels successifs.

## Décision

Quatre commandes, scopées sous le préfixe `routify:` :

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
le discoverer en `CachedRouteDiscoverer` (`routify.cache.enabled = true`),
chaque appel populate le store. Quand le cache est désactivé, la
commande **refuse** de tourner avec un message clair plutôt que de
scanner pour rien :

> *Routify cache is disabled. Set ROUTIFY_CACHE=true (or
> routify.cache.enabled = true) and retry.*

### `routify:clear`

Recalcule la même clé de cache (via
`CachedRouteDiscoverer::cacheKey()`) pour chaque couple
(path, stack), et appelle `$store->forget($key)`. La commande compte
les clés réellement oubliées. Quand le cache est désactivé, no-op
informatif (succès) :

> *Routify cache is disabled — nothing to clear.*

### `routify:optimize`

Alias atomique pour la séquence `routify:clear` + `routify:cache`. Une
seule commande à brancher dans un pipeline CI/CD ou un script de
déploiement, qui :

1. invalide le cache existant (idempotent, no-op si désactivé),
2. re-warm le cache pour la config courante.

```php
final class OptimizeCommand extends Command
{
    public function handle(): int
    {
        if ($this->call('routify:clear') !== self::SUCCESS) {
            return self::FAILURE;
        }

        return $this->call('routify:cache');
    }
}
```

C'est la commande recommandée dans le README pour le déploiement.
Branchable directement dans un Composer script (`post-install-cmd` /
`post-update-cmd`).

### Enregistrement dans le service provider

Conditionnel sur `runningInConsole()` pour éviter le coût (minime mais
réel) en contexte HTTP :

```php
if ($this->app->runningInConsole()) {
    $this->commands([
        ListCommand::class,
        CacheCommand::class,
        ClearCommand::class,
        OptimizeCommand::class,
    ]);
}
```

## Alternatives envisagées

- **Une seule commande `routify` avec sous-commandes** — rejeté :
  Symfony console ne supporte pas naturellement les sous-commandes.
  Convention Laravel = `verb:noun` (`route:cache`, `cache:clear`).
- **Pas de commande `optimize` séparée, documenter `clear && cache`
  dans le README** — rejeté : retire l'atomicité, force chaque
  utilisateur à connaître l'ordre exact, double les chances de typo
  dans les pipelines.
- **Hook `routify:optimize` directement dans `php artisan optimize`
  natif Laravel** — option future. Laravel 11+ permet d'enregistrer
  des callbacks dans `Application::optimizing()`. À explorer pour 1.1
  si la communauté le demande.

## Conséquences

- ✅ Quatre opérations distinctes, chacune avec une seule
  responsibility.
- ✅ Convention de nommage cohérente avec Laravel (`route:*`).
- ✅ `routify:optimize` rend l'intégration CI/CD trivial — une
  unique ligne dans `post-install-cmd` couvre clear + warm.
- ✅ Refus explicite quand le cache est désactivé sur `cache` (échec)
  ou `optimize` (échec via la sous-commande). `clear` reste no-op
  silencieux pour ne pas casser les pipelines qui appellent par
  prudence dans des environnements sans cache.
- ⚠️ Les commandes lisent la config et instancient leurs propres
  `StackConfig` plutôt que de passer par `RoutifyManager`. Légère
  duplication assumée pour garder les commandes dépendant uniquement
  du contract `RouteDiscoverer` et pas de toute l'API du manager.