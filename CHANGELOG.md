# Changelog

Tous les changements notables de ce bundle sont documentés dans ce fichier.

Le format suit [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/).
Le schéma de version (X.Y.Z, canaux alpha/beta/main) est décrit dans
`.github/workflows/release.yml`.

## [Unreleased]

## [1.0.0] - 2026-09-26

### Added

- Version initiale : carnet de notes d'équipe (messages visibles sur les dashboards admin et employé), portée par note (store précis ou toute l'organisation), épinglage, expiration automatique, notification des membres concernés. Première utilisation du mécanisme `database/migrations/` (voir CLAUDE.md) pour créer sa propre table `notebook_entries` plutôt que de dépendre d'une table déjà fournie par Kintai Core.
- Contrairement aux autres bundles Kintai, ce dépôt embarque une vraie suite PHPUnit (`tests/`, 24 tests) : `composer.json` mappe `kintai\` directement vers les vraies classes du dépôt Kintai principal (chemin relatif `../../Kintai/src/`, même disposition en sibling que `KintaiBundleDev/` en local) plutôt que de dupliquer des fakes. Couvre les contrôleurs Web/API (autorisations auteur-vs-`notebook.manage`, portée organisation vs store, notifications, tri/filtre épinglées-expirées) et la migration `notebook_entries` elle-même, exécutée via le vrai `BundleMigrationRunner` du Core. La CI (`tests.yml`) checkout les deux dépôts côte à côte pour reproduire cette disposition et lance la suite à chaque push/PR.
