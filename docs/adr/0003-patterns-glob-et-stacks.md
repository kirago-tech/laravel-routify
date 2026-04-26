# ADR-0003 — Patterns glob configurables et stacks comme abstraction

**Statut** : Accepté
**Date** : 2026-04-25

## Contexte

L'écosystème "auto-discovery de routes Laravel" est un terrain où
beaucoup de packages **imposent une convention figée** : « le fichier
doit s'appeler `api.php` », ou « les routes doivent vivre dans
`Routes/` ». Cette rigidité a deux problèmes majeurs :

- Toute architecture qui ne suit pas la convention est exclue d'office.
- Les variantes sémantiquement légitimes — versions (`api-v1.php`,
  `api-v2.php`), variantes internes (`api-internal.php`),
  sous-arborescences (`Routes/admin/api.php`) — ne sont pas captées.

Par ailleurs, Laravel auto-charge nativement quatre stacks : `web`,
`api`, `console`, `channels`. Beaucoup d'apps modernes en ajoutent
(`admin`, `internal-api`, `gateway`, `webhooks`, `tenant-api`…), chacun
avec son propre middleware group, son préfixe, son domaine.

## Décision

**Deux niveaux de configurabilité** se combinent.

### 1. Pattern glob par stack

Chaque stack déclare son propre `pattern` glob, passé à Symfony Finder :

```php
'api' => [
    'pattern' => 'api*.php',  // matche api.php, api-v1.php, api-internal.php…
    // …
],
```

L'utilisateur peut imposer une sous-arborescence (`Routes/api*.php`),
élargir (`**/*.php`), ou serrer (`api.php` exact).

### 2. Stacks ouverts à l'extension

`routify.stacks` est un dictionnaire `nom => attributs` librement
extensible. Les 4 stacks Laravel y sont préremplis avec leurs
middleware/préfixes habituels. L'utilisateur peut :

- Désactiver un stack (`enabled => false`)
- Modifier les attributs (`prefix`, `middleware`, `name`, `domain`,
  `pattern`)
- **Ajouter un stack custom**, ex :

```php
'admin' => [
    'enabled'    => true,
    'pattern'    => 'admin*.php',
    'middleware' => ['web', 'auth', 'admin'],
    'prefix'     => 'admin',
    'name'       => 'admin.',
    'domain'     => null,
],
```

…puis le charger via `Routify::for('admin')->load()`.

Le manager **ne hardcode aucun nom de stack**. `discoverApi()` /
`discoverWeb()` sont des raccourcis qui appellent
`loadStack($this->stack('api'))` ou `loadStack($this->stack('web'))`.

## Alternatives envisagées

- **Convention figée `api.php`/`web.php`** — rejeté : cf. contexte.
- **Pattern globs unique partagé entre stacks** — rejeté : empêche de
  scanner web et api dans le même boot avec des règles différentes.
- **Stacks hardcodés à 4** — rejeté : exclut les apps modulaires/
  multi-tenant qui sont précisément la cible du package.

## Conséquences

- ✅ Aucune contrainte structurelle imposée à l'utilisateur — Routify
  s'adapte à son arborescence, pas l'inverse.
- ✅ Versionning naturel (`api-v1.php`, `api-v2.php`) supporté sans
  config supplémentaire.
- ✅ Onboarding zéro-config pour les apps modulaires standard :
  installer le package, pointer `paths` sur le dossier modules, et tout
  fonctionne.
- ⚠️ L'utilisateur doit comprendre la syntaxe glob Symfony Finder pour
  les cas non triviaux. Documenté dans le README avec des exemples.
- ⚠️ Les stacks `console` et `channels` ont un comportement spécial
  (cf. ADR-0004). Un stack custom nommé `console` ou `channels` se
  verrait appliquer ce comportement — foot-gun théorique, jamais
  rencontré en pratique, documenté.