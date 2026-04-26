# ADR-0006 — Value objects `final readonly` et withers immuables

**Statut** : Accepté
**Date** : 2026-04-25

## Contexte

Un stack est un sac d'attributs typés : `name`, `enabled`, `pattern`,
`middleware`, `prefix`, `namePrefix`, `domain`. Ces attributs sont lus
au boot et utilisés à chaque enregistrement de route. Trois
représentations possibles :

1. **Array shape** — `['name' => 'api', 'pattern' => 'api*.php', …]`.
   Pas de typage, faute de frappe (`'patern'`) silencieuse, pas
   d'autocomplétion IDE.
2. **Class avec setters** — mutable, n'importe qui peut muter le stack
   n'importe où, perte de traçabilité.
3. **`final readonly` value object avec withers** — immuable, typé,
   chaque modification produit une nouvelle instance.

Par ailleurs, le builder `RouteStackBuilder` accumule des modifications
sur un stack avant de le passer au manager. Ce flow est plus sûr et
plus lisible si chaque modification est traçable et n'écrase rien.

## Décision

```php
final readonly class StackConfig
{
    /** @param  list<string>  $middleware */
    public function __construct(
        public string $name,
        public bool $enabled,
        public string $pattern,
        public array $middleware,
        public ?string $prefix = null,
        public ?string $namePrefix = null,
        public ?string $domain = null,
    ) {}

    public static function fromArray(string $name, array $config): self
    {
        // valide $config['pattern'], applique les défauts, construit l'instance
    }

    public function withPrefix(?string $prefix): self     { /* nouvelle instance */ }
    public function withName(?string $namePrefix): self   { /* nouvelle instance */ }
    public function withDomain(?string $domain): self     { /* nouvelle instance */ }
    public function withMiddleware(array $middleware): self { /* nouvelle instance */ }
    public function withPattern(string $pattern): self    { /* nouvelle instance */ }
}
```

- **`final readonly` (PHP 8.2+)** — la classe ne peut être étendue, ses
  propriétés ne peuvent être assignées qu'au constructeur.
- **Constructor property promotion** — le constructeur est aussi la
  déclaration des propriétés, ergonomie PHP 8.
- **`fromArray()` factory** — centralise la validation et l'application
  des défauts (`enabled = true`, `middleware = []`, etc.).
- **5 withers** — chacun retourne une nouvelle instance, l'instance
  d'origine est préservée.

Le builder `RouteStackBuilder` accumule les modifications en chaînant
ces withers :

```php
public function withPrefix(?string $prefix): self
{
    $this->stack = $this->stack->withPrefix($prefix);
    return $this;
}
```

Le builder lui-même n'est pas readonly (il accumule de l'état) mais sa
référence interne au `StackConfig` reste un value object.

## Alternatives envisagées

- **Array shape** — rejeté : pas de typage, pas d'IDE, validation
  diffuse.
- **Class mutable avec setters** — rejeté : chaque appel à un setter
  modifie l'instance, donc deux consommateurs qui partagent une
  référence se retrouvent avec des comportements imprévisibles.
- **`__clone()` magique pour les withers** — rejeté : les properties
  `readonly` ne peuvent pas être réassignées même via clone en PHP 8.2.
  Le pattern "passer toutes les propriétés au constructeur" est verbeux
  mais explicite et type-safe.

## Conséquences

- ✅ Impossible de modifier un `StackConfig` après construction. Toute
  "modification" produit une nouvelle instance. Le code lecteur peut
  tenir pour acquis qu'un `StackConfig` reçu reste constant.
- ✅ Type-checking strict — PHPStan attrape les erreurs de typage.
- ✅ La factory `fromArray()` lève `InvalidConfigurationException` au
  boot pour une config invalide. Pas de bug fantôme en runtime.
- ⚠️ Un wither verbeux (chaque méthode passe les 7 propriétés au
  constructeur). Le coût en lignes est faible et l'explicit est plus
  sûr qu'une copie magique.
- ⚠️ Une chaîne longue de withers crée plusieurs instances temporaires.
  Coût négligeable en pratique : quelques objets par stack par boot,
  collectés immédiatement.