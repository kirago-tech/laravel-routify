# ADR-0010 — Exceptions explicites, pas de `try/catch` silencieux

**Statut** : Accepté
**Date** : 2026-04-25

## Contexte

Une mauvaise configuration peut être détectée à deux moments :

- **Tôt, au boot** — en levant une exception claire qui bloque le
  démarrage de l'app et oriente vers le fix.
- **Tard, silencieusement** — le scan retourne `[]`, aucune route
  n'est enregistrée, l'app démarre normalement mais les endpoints
  n'existent pas.

La seconde option crée le pire des bugs : *"mes routes ne sont pas
chargées, mais aucune erreur visible"*. Le dev cherche dans le code
applicatif au lieu de la config.

Cas typiques de mauvaise config :

- Un path dans `routify.paths` n'existe pas (erreur de copier-coller,
  dossier supprimé)
- Un stack manque le champ `pattern`, ou le pattern est vide / non-string
- Un stack référencé via `Routify::for('admin')` n'est pas déclaré dans
  la config

## Décision

**Toute condition invalide lève une exception typée**, jamais de retour
silencieux.

### Hiérarchie d'exceptions

```php
namespace Kirago\Routify\Exceptions;

class RoutifyException extends \RuntimeException {}

final class InvalidConfigurationException extends RoutifyException {}
```

- `RoutifyException` — exceptions runtime spécifiques au package, type
  parent que l'utilisateur peut catcher en bloc.
- `InvalidConfigurationException` — sous-cas spécifique pour les
  problèmes de config, distinguable pour traitement particulier.

### Cas couverts

- `FilesystemRouteDiscoverer::discover($basePath, …)` lève
  `RoutifyException` si `is_dir($basePath)` retourne `false`.
  Message :
  > *Routify cannot scan "/path": the directory does not exist.
  > Check your routify.paths configuration.*

- `StackConfig::fromArray($name, $config)` lève
  `InvalidConfigurationException` si `$config['pattern']` est manquant,
  vide, ou non-string. Message :
  > *Stack "api" must define a non-empty string "pattern" (e.g. "api*.php").*

- `RoutifyManager::stack($name)` lève
  `InvalidConfigurationException` si le stack n'est pas dans
  `routify.stacks`. Message :
  > *Routify stack "admin" is not defined in routify.stacks.*

### Règle de codage

**Aucun `try/catch` vide nulle part dans le package.** Si une
exception est interceptée, c'est pour la transformer en exception du
package avec un message plus contextuel — jamais pour la masquer.

## Alternatives envisagées

- **Retour silencieux + log warning** — rejeté : un warning dans des
  logs jamais lus = silence. Et bloquer au boot est moins coûteux
  qu'un bug fantôme en production.
- **Exceptions PHP standard (`InvalidArgumentException`)** — rejeté :
  l'utilisateur ne peut pas catcher seulement les erreurs Routify sans
  catcher trop large. Un type dédié est plus discriminant.
- **Skip silencieux des paths inexistants** — rejeté : ouvre la porte à
  des configs où "rien n'est trouvé" est ambigu (path mort vs vraiment
  vide).

## Conséquences

- ✅ Les bugs de config se voient au boot, pas en production six mois
  plus tard.
- ✅ Les messages incluent le nom du stack et le path concerné — debug
  rapide, pas besoin de stack-trace.
- ✅ L'utilisateur peut catcher `RoutifyException` pour intercepter
  toutes les erreurs du package en bloc, ou
  `InvalidConfigurationException` pour distinguer config vs runtime.
- ⚠️ Une app qui voudrait un path optionnel (ex: scanner
  `app/Modules` *si présent*, mais ne pas planter sinon) doit gérer
  l'optionnalité en amont :
  ```php
  'paths' => array_filter([
      app_path('Modules'),
      is_dir($p = app_path('Plugins')) ? $p : null,
  ]),
  ```
  Le manager filtre déjà défensivement les non-strings, mais pas
  l'existence — délibéré pour distinguer "j'ai oublié" de "ce path
  est optionnel".