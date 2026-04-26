# ADR-0008 — `refreshNameLookups()` après chaque enregistrement de groupe

**Statut** : Accepté
**Date** : 2026-04-25

## Contexte

Bug rencontré pendant l'écriture des Feature tests Phase 4. Le manager
fait :

```php
$this->router
    ->middleware($stack->middleware)
    ->prefix($stack->prefix)
    ->name($stack->namePrefix)   // ex: 'api.'
    ->group($file);
```

…et le fichier `$file` contient :

```php
Route::get('/users', fn () => 'users')->name('users.index');
```

Comportement observé :

- ✅ `foreach (Route::getRoutes() as $r)` itère bien la route, et
  `$r->getName()` retourne `'api.users.index'` (préfixe + nom local
  concaténés correctement).
- ❌ `Route::has('api.users.index')` retourne `false`.
- ❌ `route('api.users.index')` lève `RouteNotFoundException`.
- ❌ `Route::getRoutes()->getByName('api.users.index')` retourne `null`.

### Cause racine

Quand un fichier est `require`-d dans le contexte d'un `Route::group(['as' => 'api.'], $file)`,
chaque `Route::get(...)` à l'intérieur :

1. Crée une `Route` qui hérite de l'attribut `'as' => 'api.'` du group
   stack en cours.
2. Ajoute cette route à la `RouteCollection` via `add()`. À ce moment,
   `addLookups()` indexe la route dans `nameList` sous la clé `'api.'`
   (le préfixe seul, le nom local n'a pas encore été défini).
3. Le chaining `->name('users.index')` qui suit modifie l'attribut
   `'as'` de la `Route` pour qu'il devienne `'api.users.index'`. Mais
   **`nameList` n'est pas mis à jour** — Laravel laisse l'entrée
   obsolète en l'état.

Résultat : la collection a la route, mais l'index par nom est
désynchronisé. `getByName('api.users.index')` ne trouve rien.

Cette désynchronisation existe dans toute app Laravel utilisant
`->name()` après un group avec `->name()`, mais elle ne se voit pas
en production parce que le `nameList` est rebuilt au moment du
`route:cache` (qui appelle `refreshNameLookups()` avant de sérialiser).
Sans `route:cache` (cas typique des tests Testbench, ou d'une app dev),
le bug est observable.

## Décision

Après chaque `$group->group($file)` dans
`RoutifyManager::registerRouteFile()`, appeler explicitement :

```php
$this->router->getRoutes()->refreshNameLookups();
```

Cette méthode publique de `RouteCollection` reconstruit le `nameList`
en itérant tous les routes et en ré-indexant par leur `getName()`
courant.

Un commentaire inline explique pourquoi le call est nécessaire, pour
que le futur mainteneur ne le supprime pas par méprise (ou pour qu'il
puisse le supprimer si Laravel corrige le comportement upstream).

## Alternatives envisagées

- **Ne rien faire et documenter** — rejeté : `Route::has()` /
  `route()` cassés sont une régression silencieuse pour les
  utilisateurs.
- **Lever un PR sur Laravel pour fixer `Route::name()`** — option
  parallèle, à explorer côté upstream. En attendant, le workaround est
  trivial.
- **Refresh global après tous les stacks chargés (1 fois)** au lieu de
  par fichier — micro-optimisation, mais le coût (`refreshNameLookups`
  est `O(n)` sur les routes existantes) est imperceptible à l'échelle
  d'une app standard.

## Conséquences

- ✅ `Route::has()`, `route()`, `Route::getRoutes()->getByName()`
  fonctionnent correctement après une découverte routify, comme
  attendu.
- ✅ Aucun changement d'API publique du package — fix interne.
- ⚠️ C'est un workaround d'un comportement Laravel sous-optimal. Si
  Laravel corrige son `Route::name()` pour rafraîchir le lookup
  automatiquement dans une future version, ce code deviendra inutile
  (mais pas nuisible).
- 📝 Commentaire inline en place pour le futur lecteur.