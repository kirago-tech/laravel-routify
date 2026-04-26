# ADR-0012 — Versioning par tags Git, pas de champ `version` dans `composer.json`

**Statut** : Accepté
**Date** : 2026-04-26

## Contexte

Composer permet (mais déconseille) de mettre `"version": "1.0.0"` dans
le `composer.json` d'un package. Pour un package distribué via
Packagist + Git, ce champ peut entrer en conflit avec la source
naturelle de vérité — les tags Git que Packagist crawle.

Trois positions possibles :

1. **Champ `version` dans `composer.json` synchronisé manuellement avec
   les tags** — deux sources de vérité, drift inévitable.
2. **Champ `version` dans `composer.json` qui prime sur les tags** —
   non, Packagist ignore le champ pour les versions taguées. Le champ
   ne sert que pour les packages chargés en local sans Git.
3. **Pas de champ `version` du tout, les tags Git font foi** —
   recommandation officielle de Composer.

La doc Composer dit explicitement :

> *In most cases this is not required and should be omitted. If
> Packagist is used to distribute the package, version should be
> derived from VCS tags.*

## Décision

`composer.json` **ne déclare pas** de champ `version`.

Les releases sont gérées par tags Git annotés :

```bash
git tag -a v1.0.0 -m "Release v1.0.0"
git push origin v1.0.0
```

Workflow de publication standard :

1. Merger les changements sur `main`.
2. Mettre à jour `CHANGELOG.md` avec l'entrée correspondante.
3. Tagger et pusher le tag.
4. Le webhook Packagist → GitHub détecte le push et re-crawle le repo,
   indexant la nouvelle version dans la minute.

Pour valider qu'une version est bien servie aux clients Composer :

```bash
curl -s https://repo.packagist.org/p2/kirago/laravel-routify.json
```

…retourne le manifeste consommé par `composer require`.

## Alternatives envisagées

- **Champ `version` synchronisé** — rejeté : oubli quasi-garanti, drift
  silencieux, double maintenance.
- **Outils tiers (laravel-zero/release, ergebnis/composer-normalize)**
  pour automatiser la mise à jour du champ — rejeté : ajoute une
  dépendance pour résoudre un problème qu'on n'a qu'en se le créant.

## Conséquences

- ✅ Une seule source de vérité pour la version : le tag Git annoté.
- ✅ Comportement Composer déterministe — aucun drift possible entre
  `composer.json` et les tags.
- ✅ Aligné avec la recommandation officielle Composer.
- ✅ Les releases passent par des tags annotés (`-a`), donc ont un
  message GPG-compatible et lisible dans `git tag -l -n`.
- ⚠️ Lors d'un `composer require kirago/laravel-routify` depuis le repo
  en local (path repository), Composer marquera la version en `dev-main`
  faute de tag. C'est attendu — pour une version stable, on passe par
  Packagist.