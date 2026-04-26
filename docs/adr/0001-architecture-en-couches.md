# ADR-0001 — Architecture en couches : Discovery, Support, Manager

**Statut** : Accepté
**Date** : 2026-04-25

## Contexte

Le package combine trois préoccupations très différentes qui ne demandent
pas le même environnement de test ni les mêmes dépendances :

1. **Lire le filesystem** — scanner récursivement un dossier, filtrer par
   pattern, retourner une liste de fichiers. Pure I/O, aucun besoin de
   Laravel.
2. **Modéliser un stack** — typer la config (`pattern`, `middleware`,
   `prefix`, …) et permettre des modifications fluides côté utilisateur.
   Pure logique métier, aucun besoin de Laravel.
3. **Orchestrer** — combiner 1 et 2 avec le routeur Laravel pour
   réellement enregistrer les routes ; gérer le cache, le boot, le
   service provider, les commandes Artisan.

Si on entasse tout dans un seul `RoutifyManager`, alors :

- Tester la couche 1 oblige à booter Laravel (Testbench) — feature tests
  partout, plus d'unit tests rapides.
- Le code mélange filesystem + cache + routeur, on ne peut plus
  remplacer une couche sans casser les autres (open/closed violé).

## Décision

Trois couches isolées, chacune dans son namespace :

```
src/
├── Contracts/
│   ├── RouteDiscoverer.php   ← contract de la couche 1
│   └── StackLoader.php       ← contract qui sépare le builder du manager
├── Discovery/                ← couche 1 : scan filesystem + cache
│   ├── FilesystemRouteDiscoverer.php
│   └── CachedRouteDiscoverer.php
├── Support/                  ← couche 2 : value object + builder
│   ├── StackConfig.php
│   └── RouteStackBuilder.php
├── Exceptions/
├── Facades/
├── Commands/
├── RoutifyManager.php        ← couche 3 : orchestrateur
└── RoutifyServiceProvider.php
```

Le manager est le **seul** point qui dépend du routeur Laravel et du
container. Les couches 1 et 2 sont du PHP pur testable en isolation.

## Alternatives envisagées

- **Tout dans un seul `RoutifyManager` sans contracts** — rejeté : pas
  testable sans Laravel, mélange des préoccupations.
- **Architecture hexagonale stricte avec ports et adapters partout** —
  surdimensionné pour un package de ~10 classes ; le découpage en trois
  couches suffit largement à isoler les concerns.

## Conséquences

- ✅ Les couches 1 et 2 sont testées en pur unit test (Pest 3 sans
  Testbench) — tests rapides, feedback loop courte.
- ✅ Le `CachedRouteDiscoverer` se branche sur le `FilesystemRouteDiscoverer`
  par composition (decorator). Aucune modification du filesystem
  discoverer pour ajouter le cache (cf. ADR-0005).
- ✅ Un utilisateur avancé peut implémenter son propre `RouteDiscoverer`
  ou son propre `StackLoader` (ex: pour une découverte multi-tenant) en
  branchant son code à la place du nôtre via le container.
- ⚠️ Trois fichiers à parcourir pour suivre une route depuis le scan
  jusqu'à `Route::group()`. Le README et les ADRs réduisent ce coût en
  exposant la carte mentale.