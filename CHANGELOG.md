# Changelog

Tous les changements notables de ce bundle sont documentés dans ce fichier.

Le format suit [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/).
Le schéma de version (X.Y.Z, canaux alpha/beta/main) est décrit dans
`.github/workflows/release.yml`.

## [Unreleased]

### Changed

- Garde-fou contre les handlers inline, en prévision de la Content-Security-Policy stricte de Kintai Core 0.3.0 (`script-src 'self' 'nonce-…'`, sans `'unsafe-inline'`) : `tests.yml` échoue désormais si un attribut `onclick=`/`onchange=`/`onsubmit=`/`oninput=`, un lien `javascript:` ou un `<script>` sans nonce apparaît dans `Views/` ou `src/` — le navigateur les bloquerait en silence, sans aucune erreur côté serveur. **Aucun changement fonctionnel** : les vues de ce bundle n'utilisent déjà aucun handler inline ni `<script>` inline exécutable. La règle est documentée dans `CONTRIBUTING.md` et `CLAUDE.md`.

### Changed

- Le CSS du widget (`.notebook-widget-list`/`.notebook-widget-item*`) vivait physiquement dans Kintai Core (`public/assets/css/src/components/notebook.css`), pas dans ce dépôt — le bundle en dépendait silencieusement sans jamais le fournir lui-même, cassant l'isolation que la distribution en dépôt séparé est censée garantir. Il vit maintenant dans `public/css/notebook.css`, servi via le nouveau mécanisme `Bundle::loadAssetsFrom()`/`bundle_asset()` de Kintai Core, chargé par `Views/notebook.php` **et** par le widget dashboard natif du Core (qui s'aligne désormais sur ce fichier au lieu d'en garder sa propre copie). **Nécessite** `kintai_core.min: "0.2.0"` (voir `docs/creating-a-bundle.md`'s "Assets" côté Core).

## [1.1.0] - 2026-09-29

### Fixed

- `routes.php` : la route d'index (`GET /notebook`) et la route de création (`POST /notebook`) étaient déclarées avec le motif `/` à l'intérieur du groupe `/notebook`, ce qui compilait en `/notebook/` — un chemin que `Router::dispatch()` ne peut jamais atteindre puisqu'il normalise systématiquement l'URI entrante en retirant le `/` final. Résultat : `GET /notebook` renvoyait toujours `No route matches`, même bundle activé. Aucun autre bundle Kintai n'utilise ce motif pour sa route d'index (voir par ex. `timeoff`/`store-photos`, qui nomment le segment complet) — corrigé en utilisant le motif vide `''` pour hériter du préfixe du groupe tel quel.

### Changed

- La notification envoyée à la publication d'une note ne disait rien de son contenu et ne menait nulle part au clic. Utilise le nouveau `$link` de `NotificationService::notify()` (Kintai Core, PR séparée) pour renvoyer vers `/notebook`, et enrichit le corps du message avec l'auteur et un extrait de la note (`notif_notebook_entry_created_body` gagne les placeholders `:author`/`:excerpt`, fr/en/ja). **Nécessite** la version de Kintai Core qui introduit le paramètre `$link` sur `notify()`/`notifyMany()` — un appel à 6 arguments contre une version de Core encore sur l'ancienne signature à 5 lèverait une erreur PHP.

## [1.0.0] - 2026-09-26

### Added

- Version initiale : carnet de notes d'équipe (messages visibles sur les dashboards admin et employé), portée par note (store précis ou toute l'organisation), épinglage, expiration automatique, notification des membres concernés. Première utilisation du mécanisme `database/migrations/` (voir CLAUDE.md) pour créer sa propre table `notebook_entries` plutôt que de dépendre d'une table déjà fournie par Kintai Core.
- Contrairement aux autres bundles Kintai, ce dépôt embarque une vraie suite PHPUnit (`tests/`, 24 tests) : `composer.json` mappe `kintai\` directement vers les vraies classes du dépôt Kintai principal (chemin relatif `../../Kintai/src/`, même disposition en sibling que `KintaiBundleDev/` en local) plutôt que de dupliquer des fakes. Couvre les contrôleurs Web/API (autorisations auteur-vs-`notebook.manage`, portée organisation vs store, notifications, tri/filtre épinglées-expirées) et la migration `notebook_entries` elle-même, exécutée via le vrai `BundleMigrationRunner` du Core. La CI (`tests.yml`) checkout les deux dépôts côte à côte pour reproduire cette disposition et lance la suite à chaque push/PR.
