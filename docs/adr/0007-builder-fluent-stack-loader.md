# ADR-0007 — Builder fluent et contract `StackLoader` extrait

**Statut** : Accepté
**Date** : 2026-04-25

## Contexte

Pour les cas que la config statique ne peut pas exprimer (ex:
*"charger l'api uniquement depuis un sous-dossier précis avec un
préfixe override"*), le package expose un builder fluent :

```php
Routify::for('api')
    ->in(app_path('Modules/Billing'))
    ->withPrefix('api/v2')
    ->withMiddleware(['api', 'throttle:60,1'])
    ->withName('api.v2.')
    ->matching('api-v2*.php')
    ->load();
```

Pour fonctionner, ce builder doit pouvoir appeler `loadStack()` sur le
manager final. Si on type-hint **directement** `RouteStackBuilder` sur
`RoutifyManager`, alors :

- `RoutifyManager` ne peut plus être `final` (la règle "final sauf si
  pensée pour extension"), parce qu'on aurait besoin de le mocker dans
  les tests unitaires du builder.
- Le builder devient inutilisable hors du manager. Pas extensible —
  par exemple, un système custom multi-tenant qui voudrait réutiliser
  le builder avec son propre loader devrait forker.

## Décision

Extraire un mini-contract `Kirago\Routify\Contracts\StackLoader` :

```php
interface StackLoader
{
    /**
     * @param  list<string>|null  $pathsOverride
     */
    public function loadStack(StackConfig $stack, ?array $pathsOverride = null): void;
}
```

- `RouteStackBuilder` type-hint sur ce contract dans son constructeur.
- `RoutifyManager implements StackLoader`.
- Dans les unit tests du builder, on instancie un faux `StackLoader`
  (anonymous class spy) qui capture les arguments — aucun besoin de
  Laravel.

```php
function spyLoader(): StackLoader
{
    return new class implements StackLoader {
        public ?StackConfig $received = null;
        public ?array $receivedPaths = null;
        public int $calls = 0;

        public function loadStack(StackConfig $stack, ?array $pathsOverride = null): void
        {
            $this->calls++;
            $this->received = $stack;
            $this->receivedPaths = $pathsOverride;
        }
    };
}
```

Le builder est ainsi entièrement testable en pur unit test (6 tests
Pest, 0 dépendance Laravel).

## Alternatives envisagées

- **Type-hint direct sur `RoutifyManager`** — rejeté : forcerait à
  retirer `final`, ou à mocker un `final`, ou à passer par un faux
  manager qui hérite de l'interface — exactement ce qu'on évite avec
  un contract.
- **Closure passée au builder** (`new RouteStackBuilder(fn (StackConfig $s) => …)`) —
  rejeté : moins lisible qu'une interface nommée, perd le typage du
  paramètre `pathsOverride`.

## Conséquences

- ✅ `RoutifyManager` reste `final`, conformément à la règle « final
  par défaut ».
- ✅ Builder testable en pur unit test (Pest sans Testbench), feedback
  loop courte.
- ✅ Un utilisateur avancé peut implémenter son propre `StackLoader`
  (ex: un loader multi-tenant qui résout les paths dynamiquement par
  tenant) et plug le builder dessus via le container.
- ⚠️ Une interface de plus à comprendre quand on lit le code. Le nom
  (`StackLoader`) la rend immédiatement explicable, et le commentaire
  inline du contract précise la sémantique de `pathsOverride`.