# ADR-0008 — `RouteCollection::refreshNameLookups()` consolidé en un appel par `loadStack()`

**Statut** : Accepté
**Date** : 2026-04-26

## Contexte

Bug rencontré pendant l'écriture des Feature tests. Le manager fait :

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
  `$r->getName()` retourne `'api.users.index'`.
- ❌ `Route::has('api.users.index')` retourne `false`.
- ❌ `route('api.users.index')` lève `RouteNotFoundException`.

### Cause racine

Quand un fichier est `require`-d dans le contexte d'un
`Route::group(['as' => 'api.'], $file)`, chaque `Route::get(...)` à
l'intérieur :

1. Crée une `Route` qui hérite de l'attribut `'as' => 'api.'` du group.
2. L'ajoute à la `RouteCollection` via `add()`. À ce moment-là,
   `addLookups()` indexe la route dans `nameList` sous la clé `'api.'`
   (le préfixe seul, le nom local n'a pas encore été défini).
3. Le chaining `->name('users.index')` modifie l'attribut `'as'` pour
   qu'il devienne `'api.users.index'`, **mais `nameList` n'est pas
   mis à jour** — Laravel laisse l'entrée obsolète en l'état.

Cette désynchronisation existe dans toute app Laravel utilisant
`->name()` après un group avec `->name()`, mais elle ne se voit pas en
production parce que `route:cache` appelle `refreshNameLookups()`
avant de sérialiser. Sans `route:cache` (cas typique en dev et dans
les tests Testbench), le bug est observable.

## Décision

À la fin de chaque `loadStack()`, **après avoir chargé tous les
fichiers du stack**, on rafraîchit le name lookup une seule fois :

```php
public function loadStack(StackConfig $stack, ?array $pathsOverride = null): void
{
    $files = [/* … collecte dédupliquée … */];

    if ($files === []) {
        return;
    }

    $touchedRouter = false;
    foreach (array_keys($files) as $file) {
        $touchedRouter = $this->registerRouteFile($stack, $file) || $touchedRouter;
    }

    if ($touchedRouter) {
        $this->router->getRoutes()->refreshNameLookups();
    }
}
```

Trois précisions :

- **Court-circuit sur stack vide** — si aucun fichier n'a matché, on
  sort sans rien toucher au router. Cas fréquent quand un stack
  optionnel est activé mais sans fichier correspondant.
- **Court-circuit sur stack non-HTTP** — `registerRouteFile()` renvoie
  `bool` : `false` quand le fichier était CLI ou broadcast (cf.
  ADR-0004). Si aucun fichier n'a touché le router, le rafraîchissement
  est skip. Pertinent pour les stacks `console`/`channels`.
- **Une fois par `loadStack()`, pas une fois par fichier** — le call
  initial était dans `registerRouteFile()`, donc invoqué `O(N)` fois
  pour `N` fichiers. Le coût total devenait `O(N · M)` où `M` est le
  nombre de routes déjà enregistrées. Le déplacer hors de la boucle
  donne `O(M)` total, indépendant de `N`.

Un commentaire inline explique pourquoi le call est nécessaire, pour
que le futur mainteneur ne le supprime pas par méprise — ou puisse le
supprimer si Laravel corrige le comportement upstream.

## Alternatives envisagées

- **Ne rien faire et documenter** — rejeté : `Route::has()` / `route()`
  cassés sont une régression silencieuse pour les utilisateurs.
- **Refresh une seule fois à la fin de `discover()` (tous stacks)** —
  micro-optimisation supplémentaire, mais le gain est nul (un appel
  `refreshNameLookups()` coûte `O(M)` que ce soit une fois par stack
  ou une fois pour tous). Et un refresh par stack est plus localisé
  : si un stack lève une exception en cours de chargement, les stacks
  déjà chargés gardent un nameList cohérent.
- **PR sur Laravel pour fixer `Route::name()` upstream** — option
  parallèle, à explorer. Le workaround est trivial en attendant.

## Conséquences

- ✅ `Route::has()`, `route()`, `Route::getRoutes()->getByName()`
  fonctionnent correctement après une découverte routify.
- ✅ Coût `O(routes_déjà_enregistrées)` une fois par `loadStack()` —
  négligeable même pour des suites de routes très longues.
- ✅ Aucun appel inutile sur des stacks vides ou non-HTTP.
- ⚠️ C'est un workaround d'un comportement Laravel sous-optimal. Si
  Laravel corrige son `Route::name()` upstream, ce code deviendra
  inutile (mais pas nuisible).
- 📝 Commentaire inline en place pour le futur lecteur.