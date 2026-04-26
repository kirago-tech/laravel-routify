# ADR-0002 — Symfony Finder comme moteur de scan filesystem

**Statut** : Accepté
**Date** : 2026-04-25

## Contexte

Le rôle de `FilesystemRouteDiscoverer::discover($basePath, $pattern)`
est de retourner une liste triée de chemins absolus correspondant à
`$pattern` sous `$basePath`, récursivement. Trois moyens existent :

1. **`glob()` PHP natif** — non récursif (sauf à composer plusieurs
   appels), pas de glob avancé (`**/*.php` non supporté), API maladroite.
2. **`RecursiveDirectoryIterator` + `RegexIterator`** — verbeux,
   convertir un glob en regex à la main, code à maintenir.
3. **`Symfony\Component\Finder\Finder`** — déclaratif, glob natif,
   récursif par défaut, déjà présent transitively via `illuminate/filesystem`.

## Décision

Utiliser `Symfony\Component\Finder\Finder` :

```php
$finder = (new Finder)
    ->files()
    ->in($basePath)
    ->name($pattern);

$files = [];
foreach ($finder as $file) {
    $files[] = self::normalize($file->getPathname());
}

sort($files);

return array_values($files);
```

La dépendance est **explicite** dans `composer.json`
(`symfony/finder: ^7.0`) plutôt que de compter sur la transitivité — un
upgrade futur d'Illuminate qui retirerait cette dépendance ne casserait
pas Routify.

Les chemins sont normalisés en remplaçant les séparateurs Windows (`\`)
par des slashs avant le tri, pour obtenir un ordre déterministe
indépendant du système.

## Alternatives envisagées

- **Implémentation maison `RecursiveDirectoryIterator`** — rejeté : on
  réécrirait Symfony Finder en moins testé.
- **`glob('**\/*.php')` avec le flag `GLOB_BRACE`** — rejeté :
  comportement Windows/Linux non uniforme et pas de récursion.

## Conséquences

- ✅ Le contract `RouteDiscoverer::discover()` est presque entièrement
  délégué à Finder. Le code restant tient en 30 lignes lisibles.
- ✅ Les patterns globs Symfony (`*`, `?`, `**`) sont supportés sans
  effort.
- ✅ Symfony Finder est ultra-stable, maintenu par Symfony depuis 2009.
- ⚠️ Symfony Finder lève `DirectoryNotFoundException` si le path
  n'existe pas. On l'intercepte via un check `is_dir()` explicite en
  amont pour lever notre propre `RoutifyException` (cf. ADR-0010), afin
  de ne pas exposer un type d'exception Symfony aux utilisateurs.