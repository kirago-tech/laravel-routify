# ADR-0013 — Découverte par dossier en coexistence avec les patterns

**Statut** : Accepté
**Date** : 2026-05-26

## Contexte

La version 1.0 découvre les fichiers de routes par **glob sur le nom**
(`api*.php`, `web*.php`, …). Cette approche est précise mais impose à
l'utilisateur de renommer ses fichiers pour qu'ils matchent le pattern d'un
stack. Le frottement est particulièrement sensible pour les **projets Laravel
existants** qui adoptent le package : leur arborescence en place ne correspond
pas forcément aux conventions de Routify.

Deux philosophies opposées étaient envisageables pour 1.1 :

- **Remplacer** le mode pattern par une convention by-folder pure : plus simple
  à expliquer, mais casse tous les déploiements 1.0 et n'aide pas les configs
  custom qui ont des patterns volontairement précis.
- **Étendre** le modèle pour qu'un fichier puisse être rattaché à un stack
  *aussi* via la position dans l'arborescence : ergonomie additive, zéro casse,
  mais introduit deux règles d'appartenance simultanées.

## Décision

**Coexistence des deux modes.** À partir de 1.1, un fichier `.php` appartient
à un stack si **l'une** de ces conditions est vraie :

1. **Convention by-folder** — son chemin relatif au `paths[]` contient un
   segment exactement égal à la clé du stack (`api`, `web`, ou n'importe quelle
   clé personnalisée), à n'importe quelle profondeur.
2. **Pattern v1** — son nom matche le `pattern` glob du stack.

Un fichier qui satisfait les deux est enregistré exactement une fois grâce au
dédoublonnage en amont de la registration. Un fichier qui ne satisfait aucune
des deux est silencieusement ignoré, comme en 1.0.

Le `pattern` devient optionnel par stack (`?string` au lieu de `string`) : un
stack peut désormais reposer entièrement sur la convention by-folder.
`StackConfig::fromArray()` ne lève plus d'exception sur `pattern` manquant ou
vide — c'est une *relaxation* de la validation existante, pas un retrait.

## Théorème de compat (garantie mécanique)

```text
fichiers_chargés_par_v1.1(C)  ⊇  fichiers_chargés_par_v1.0(C)
```

Pour toute configuration `C` valide en 1.0, l'ensemble des fichiers chargés en
1.1 est un sur-ensemble de celui chargé en 1.0. Cette propriété découle
directement du design :

- `RoutifyManager` continue d'appeler `discover($path, $stack->pattern)` avec
  les **mêmes arguments** qu'en 1.0 (méthode inchangée).
- Il y *ajoute* `discoverInFolder($path, $stack->name)`. Cette liste peut être
  vide, jamais soustractive.
- `array_unique` + `sort` produit un sur-ensemble ordonné, déterministe.

**Critère de preuve** : la suite Pest 1.0 passe sans modification en 1.1.

## Alternatives envisagées

- **Tout migrer vers by-folder, retirer `pattern`.** Rejeté : casse les
  installations existantes et oblige tous les utilisateurs à réorganiser leurs
  dossiers pour upgrader. Incompatible avec un bump mineur.
- **`pattern` toujours requis, by-folder géré par un sous-pattern type
  `api/**/*.php`.** Rejeté : verbeux, fait fuir la valeur "drop & go". Le glob
  Symfony Finder n'a pas non plus la sémantique de "segment exact" hors d'un
  filtre custom.
- **Flag global `discovery.by_folder` opt-in.** Rejeté : la fonctionnalité
  serait alors invisible des utilisateurs qui n'ont pas lu le changelog — donc
  inutile en pratique. La coexistence par défaut est plus loyale.
- **Opt-out par stack (un `discovery: pattern_only` sur chaque stack).**
  Rejeté pour l'instant : ré-introduit un knob de config, complique la doc, et
  le cas réel d'usage est trop rare pour justifier l'ergonomie en moins. À
  ré-évaluer si la demande se manifeste.

## Conséquences

- ✅ Onboarding fluide pour les projets existants : aucun renommage nécessaire,
  on adopte progressivement le by-folder là où ça simplifie.
- ✅ Stacks custom triviaux : déclarer un stack `admin` et créer un dossier
  `admin/` suffit, sans même définir un pattern.
- ✅ Compat 1.0 garantie mécaniquement par le théorème de superset.
- ⚠️ Un dossier *sous un `paths[]` scanné* qui porte par hasard le nom d'un
  stack (`api/`, `web/`, …) verra ses fichiers `.php` désormais découverts —
  même s'ils ne sont pas des fichiers de routes (helpers, classes oubliées,
  …). C'est le revers logique de la convention. Documenté dans le README et le
  CHANGELOG sous une *Migration note*. Mitigation utilisateur : restreindre
  `paths`, ou renommer le dossier.
- ⚠️ Le cache de découverte distingue désormais deux familles de clés
  (`pattern:` et `folder:`) — l'`xxh128` du couple `(basePath, pattern)` ne
  doit pas collisionner avec celui de `(basePath, folderName)`. C'est garanti
  par le préfixe explicite `routify:files:folder:` dans `folderCacheKey()`.
- ⚠️ `RoutifyManager::loadStack` doit valider explicitement l'existence du
  `paths[]` quand le stack n'a pas de pattern, puisque `discoverInFolder()`
  retourne `[]` sans lever d'exception si la base est absente. Le `discover()`
  v1.0 gardait cette responsabilité ; on la fait remonter d'un cran pour ne
  pas perdre la garantie *« missing paths throw »* exigée par
  [ADR-0010](0010-exceptions-explicites.md).