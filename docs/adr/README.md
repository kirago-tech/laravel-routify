# Architecture Decision Records

Ce dossier consigne les décisions architecturales structurantes du package
`kirago/laravel-routify`. Chaque ADR explique le **pourquoi** d'un choix —
son contexte, les alternatives écartées et les conséquences — pour qu'un
mainteneur futur (ou un utilisateur curieux) puisse comprendre la logique
sans avoir à reconstituer l'historique commit par commit.

Le format est inspiré de [Michael Nygard, *Documenting Architecture
Decisions*](https://cognitect.com/blog/2011/11/15/documenting-architecture-decisions).

## Conventions

- **Langue** : français.
- **Numérotation** : 4 chiffres (`0001`, `0002`, …) pour le tri lexicographique.
- **Immutabilité** : un ADR accepté ne se modifie plus. Pour revisiter une
  décision, on en crée un nouveau qui marque l'ancien comme `Remplacé par`.
- **Format** : copier [`0000-template.md`](0000-template.md) et remplir.

## Index

### Architecture générale
- [ADR-0001 — Architecture en couches : Discovery, Support, Manager](0001-architecture-en-couches.md)

### Découverte des fichiers de routes
- [ADR-0002 — Symfony Finder comme moteur de scan filesystem](0002-symfony-finder.md)
- [ADR-0003 — Patterns glob configurables et stacks comme abstraction](0003-patterns-glob-et-stacks.md)
- [ADR-0004 — Console et channels : bypass de `Route::group()`](0004-bypass-route-group-console-channels.md)
- [ADR-0013 — Découverte par dossier en coexistence avec les patterns (1.1)](0013-decouverte-par-dossier.md)

### Cache de découverte
- [ADR-0005 — Cache opt-in via decorator et clé hashée par couple `(basePath, pattern)`](0005-cache-decorator-cle-hashee.md)
- [ADR-0014 — Cache auto-heal et escape hatch des commandes `routify:*`](0014-cache-auto-heal.md)

### API publique
- [ADR-0006 — Value objects `final readonly` et withers immuables](0006-value-objects-readonly.md)
- [ADR-0007 — Builder fluent et contract `StackLoader` extrait](0007-builder-fluent-stack-loader.md)

### Manager et bootstrap
- [ADR-0008 — `refreshNameLookups()` après chaque enregistrement de groupe](0008-refresh-name-lookups.md)
- [ADR-0009 — Auto-discovery au boot, opt-in mais activée par défaut](0009-auto-discover-opt-in.md)

### Qualité et publication
- [ADR-0010 — Exceptions explicites, pas de `try/catch` silencieux](0010-exceptions-explicites.md)
- [ADR-0011 — Quatre commandes Artisan : `list`, `cache`, `clear`, `optimize`](0011-commandes-artisan.md)
- [ADR-0012 — Versioning par tags Git, pas de champ `version` dans `composer.json`](0012-versioning-par-tags-git.md)