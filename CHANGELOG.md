# Changelog

Tous les changements notables de ce bundle sont documentés dans ce fichier.

Le format suit [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/).
Le schéma de version (X.Y.Z, canaux alpha/beta/main) est décrit dans
`.github/workflows/release.yml`.

## [Unreleased]

### Added

- Version initiale : carnet de notes d'équipe (messages visibles sur les dashboards admin et employé), portée par note (store précis ou toute l'organisation), épinglage, expiration automatique, notification des membres concernés. Première utilisation du mécanisme `database/migrations/` (voir CLAUDE.md) pour créer sa propre table `notebook_entries` plutôt que de dépendre d'une table déjà fournie par Kintai Core.
