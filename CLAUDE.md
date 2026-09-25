# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this repository is

This is the standalone distribution repo for the official "Notebook" bundle of
[Kintai](https://github.com/AudricSan/Kintai) — a team notebook: members post
notes visible on both the admin and employee dashboards, with pinning,
per-note scope (a single store, or the whole organization), and automatic
expiration. It follows the same distribution model as every other Kintai
bundle (see `docs/creating-a-bundle.md` in the main Kintai repo — manifest,
registry, installer), but is the first bundle to ship its own database schema
via the `database/migrations/` mechanism (see "Architecture" below) rather
than depending on a table Kintai Core already provides.

There is no build or test suite in this repo (no `composer.json`, no
PHPUnit). The code is not runnable or functionally testable standalone: every
class under `src/` depends on `kintai\Core\*` (repositories, middleware,
`Request`/`Response`, `ViewRenderer`, `PermissionService`, etc.) that only
exist inside a running Kintai instance. Verifying a behavior change means
installing the bundle into a real Kintai instance, not running anything in
this repo. CI here only checks PHP syntax and manifest validity (see
"CI and branches" below) — it cannot catch logic errors.

Kintai never `git clone`/`pull`s bundles (many shared-hosting environments
have no `git` CLI available to PHP) — `BundleInstallerService` always
downloads a tagged GitHub Release's zipball. This repo's only "build output"
is therefore the GitHub Release itself; nothing here gets compiled or
packaged.

## CI and branches

This repo mirrors the branch/release model of the main Kintai repo:

- `main`, `alpha`, and `beta` are protected branches — no direct push; land
  changes via a PR (see `CONTRIBUTING.md`). New work targets `alpha` (the
  active channel); promote a line forward by merging `alpha` → `beta` → `main`.
- `.github/workflows/tests.yml` runs a `test` job (PHP syntax check via
  `php -l` on every `.php` file under `src/`, `Views/`, and `database/`, plus
  JSON validation of `bundle.json` and `lang/*.json`) on every push and PR to
  these branches. This is the required status check gating merges.
- Merging into any of the three branches triggers
  `.github/workflows/release.yml`, which tags and publishes a GitHub Release
  — see "Release process" below.

## Release process

`.github/workflows/release.yml` triggers on push to `alpha`, `beta`, or
`main` (i.e. on every merge, since those branches are protected) and computes
and pushes the tag itself — never tag or `gh release create` by hand:

- Version line `X.Y` comes from `version` in `bundle.json`, which is always
  written as the placeholder `X.Y.0` and is only bumped by hand when opening a
  new release line (new `Y`).
- `alpha`/`beta` merges tag `vX.Y.Z` as a prerelease, where `Z` is the highest
  existing `vX.Y.*` tag + 1 — a counter shared and cumulative across alpha and
  beta within the same line, never reset between them.
- `main` merges tag `vX.Y.0` as the stable release for that line. If `vX.Y.0`
  already exists, the job skips cleanly (a line only ever gets one stable
  release; further fixes require opening a new line).
- Release notes are extracted from `CHANGELOG.md`: `## [Unreleased]` for
  alpha/beta (falling back to `## [X.Y.0]` if `Unreleased` is empty, i.e. the
  release commit already renamed it), or `## [X.Y.0]` directly for `main`. A
  push to a channel with no matching CHANGELOG section fails the job — always
  update `CHANGELOG.md` in your PR before merging.

## Architecture

- `bundle.json` — manifest read by Kintai's bundle installer/registry: slug,
  version, `kintai_core` compatibility range, `entry_class`. `kintai_core.min`
  must be at or above the Kintai version that introduced bundle-owned
  migrations (`BundleMigrationRunner`, see below) — an older Core silently
  ignores `database/migrations/`, leaving the bundle installed with no table.
- `src/NotebookBundle.php` — the entry point (`kintai\Bundles\Installed\Notebook\NotebookBundle`,
  extends `kintai\Core\BundleContract\Bundle`). Its `register()` binds
  `NotebookEntryRepositoryInterface` to `DatabaseNotebookEntryRepository` as a
  singleton in the app container, then calls `loadViewsFrom(..., 'notebook')`
  and `loadRoutesFrom(routes.php)`. Like Feedback's `FeedbackRepositoryInterface`,
  this binding only happens when the bundle is active — Kintai Core's
  dashboard widgets guard every access with `Container::has(...)`, so nothing
  crashes while this bundle isn't installed.
- `database/migrations/2026_09_25_000001_create_notebook_entries_table.php` —
  creates the `notebook_entries` table (`author_id`, nullable `store_id` where
  `null` means "visible to the whole organization", `content`, `pinned`,
  `expires_at`, timestamps). Same file format as Kintai's own Core migrations
  (`return new class($this->capsule) extends Migration {...}`), run
  automatically by `BundleMigrationRunner` at install/update time (tracked in
  a `bundle_migrations` table, scoped by bundle slug — never Kintai's own
  `migrations` table). This is the first bundle to use this mechanism; see
  Kintai's `docs/creating-a-bundle.md` → "Database migrations" for the full
  contract. `NotebookEntryRepositoryInterface`/`DatabaseNotebookEntryRepository`/
  the Eloquent model itself live in Kintai Core (`src/Core/Repositories/`,
  `src/Domain/Eloquent/`), following the same "stable interface" convention
  every other bundle uses — only the table's *creation* is delegated to this
  bundle, not the repository contract.
- `routes.php` — two route groups: `/notebook/*` (`AuthMiddleware` +
  `PermissionMiddleware`, permissions `notebook.view`/`notebook.create`/`notebook.manage`)
  and `/api/v1/notebook-entries` (`ApiAuthMiddleware` + `ApiPermissionMiddleware`).
  Unlike Feedback/Messaging, there's no `/admin` vs `/employee` split — access
  is purely RBAC-driven (an Owner can grant `notebook.create` to the Employee
  role if they want self-service posting, or keep it Manager-only).
- `src/Controllers/Web/NotebookController.php` — `index()` lists notes visible
  to the user (their managed/member stores + organization-wide notes),
  pinned first, hiding expired ones unless `?show_expired=1`. `store()`
  validates content, resolves `store_id` (empty = organization-wide, only
  allowed for a globally-scoped user — `managed_store_ids === null`), saves,
  audit-logs, and notifies (`notifyMembers()`) every user holding
  `notebook.view` in scope. `update()`/`destroy()` are allowed for the note's
  author OR anyone holding `notebook.manage` on its store; `togglePin()` is
  gated to `notebook.manage` at the route level.
- `src/Controllers/Api/NotebookController.php` — REST CRUD, `restrictToScope`/
  ownership pattern (author OR `notebook.manage`) matching every other
  bundle's API controller — see `ShiftSwapRequestController` in Core if you
  need the reference audit-fix history for this pattern.
- `Views/notebook.php` — the full list/management page, registered under the
  `notebook::` view namespace (`notebook::notebook`). Reuses the
  `.notebook-widget-item` CSS classes Kintai Core ships for its own dashboard
  widget cards (`public/assets/css/src/components/notebook.css`), so this
  page and the dashboard widgets look identical.
- `lang/{en,fr,ja}.json` — bundle-scoped translation keys, merged into
  Kintai's `__()` translator. `bundle_notebook`/`bundle_notebook_desc` are
  required in every locale (used by `getLabel()`/`getDescription()`). Keys
  already defined in Kintai Core (`widget_team_notes`, `no_notebook_entries`,
  `notebook_pinned`, `notebook_org_wide`, `notebook_anonymous_author` — used
  by the Core dashboard widgets this bundle's data feeds) are **not**
  redeclared here; only vocabulary exclusive to this bundle's own pages is.
