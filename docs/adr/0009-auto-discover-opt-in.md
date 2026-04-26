# ADR-0009 — Auto-discovery au boot, opt-in mais activée par défaut

**Statut** : Accepté
**Date** : 2026-04-25

## Contexte

Deux philosophies pour un package d'auto-discovery :

- **Magique** — ça marche dès l'install, l'utilisateur ne fait rien.
  Le service provider appelle `discover()` au boot, point.
- **Explicite** — l'utilisateur déclare lui-même `Routify::discover()`
  dans son `AppServiceProvider::boot()` ou son `bootstrap/app.php`.

Chaque approche a ses partisans :

- La magie facilite l'onboarding et minimise le boilerplate. Pour la
  cible principale du package (apps modulaires standard), c'est le bon
  défaut.
- L'explicite est audit-friendly : un dev qui hérite du projet voit
  noir sur blanc *où* les routes sont chargées et *quand*.

## Décision

Une bascule unique dans la config :

```php
'auto_discover_on_boot' => true,
```

Quand `true`, `RoutifyServiceProvider::boot()` appelle automatiquement
`RoutifyManager::discover()` qui parcourt tous les stacks activés et
charge tout :

```php
public function boot(): void
{
    // …
    if ($this->app->make(Config::class)->get('routify.auto_discover_on_boot')) {
        $this->app->make(RoutifyManager::class)->discover();
    }
}
```

Quand `false`, l'utilisateur reprend le contrôle :

```php
// AppServiceProvider::boot()
Routify::discoverApi();        // uniquement l'api
Routify::discoverApi('api/v1'); // avec un préfixe override
Routify::for('admin')->load();  // un stack custom
```

**Valeur par défaut : `true`**. Décision prise après concertation : le
package vise majoritairement des apps modulaires qui veulent de la
magie. Les utilisateurs avancés ont la bascule à un seul flag.

## Alternatives envisagées

- **Pas d'auto-discovery, utilisateur appelle toujours
  `Routify::discover()`** — rejeté : verbeux pour le cas commun,
  introduit un point de défaillance (oubli de l'appel) sans gain.
- **Auto-discovery toujours active, pas de bascule** — rejeté : retire
  un degré de liberté légitime aux apps complexes (multi-tenant,
  lazy-loading par contexte, etc.).
- **Détection automatique : auto-discover si certains stacks sont
  enabled, sinon non** — rejeté : magie sur la magie, comportement
  imprévisible, plus difficile à expliquer.

## Conséquences

- ✅ Onboarding zéro-config pour le cas commun : `composer require`,
  pointer `routify.paths` sur le dossier modules, et tout marche.
- ✅ Sortie facile : un flag dans la config, pas de désinscription
  complexe ou de réécriture du service provider.
- ✅ Les apps qui veulent lazy-loader certains stacks (ex:
  `discoverConsole()` uniquement quand `runningInConsole()`) gardent
  le contrôle.
- ⚠️ Un utilisateur qui n'a pas lu le README peut être surpris que ses
  routes se chargent toutes seules au boot du package. Le README
  ouvre par-dessus cette bascule, et la config publiée
  (`vendor:publish`) est commentée inline.