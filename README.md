# Atlas ERP: a legacy migration case

[![ci](https://github.com/jonatanaxe/laravel-legacy/actions/workflows/ci.yml/badge.svg)](https://github.com/jonatanaxe/laravel-legacy/actions/workflows/ci.yml)

Technical case prepared for the PALO IT interview (AI Native Software
Engineer). Atlas ERP is the system of a fictional client: Laravel **5.8.38** on
PHP **7.2.34**, used as the "before" of a real migration to PHP 8.4. It has a
dashboard, tasks, projects with roles, authorized attachments, a reminder
queue, CSV import, an audit trail, a token API, a health endpoint, CI and a
billing module inherited from 2009 (`app/Legacy`).

The front end is legacy too: Tailwind 1.9 and Alpine 2.8, the versions a
Laravel 5.8 app would have adopted. Their upgrade is part of the plan.

## Docs

| Doc | What's in it |
|---|---|
| [Engineering brief](docs/cto-brief.md) | Measured numbers, decisions and why, known risks, the migration plan and the 6-minute demo runbook |
| [Security practices](docs/security.md) | Each protection, where it lives in the code and how to attack it to see the defense |
| [CI/CD](docs/ci.md) | The pipeline, what each job proves, its artifacts, what CI has caught and how deployment would work |
| [Legacy answer key](docs/legacy-answer-key.md) | The 26 planted legacy problems, the version each one breaks on and the test that catches it |

## Running it

Needs PHP **7.2** (with `pdo_sqlite`, `mbstring`, `curl`, `dom`), Composer
and Node. The quickest path uses SQLite, the same setup CI runs:

```bash
cp .env.example .env
composer install
php artisan key:generate
touch database/database.sqlite
# in .env: DB_CONNECTION=sqlite, DB_DATABASE=<absolute path to database/database.sqlite>
php artisan migrate:fresh --seed
npm ci --prefix tools/tw           # Tailwind 1 CLI, kept apart from the legacy package.json
npm run css:build                  # writes public/css/tailwind.css (not versioned)
php artisan serve                  # http://127.0.0.1:8000
```

The numbers in `docs/` were measured on a local Docker environment (MariaDB,
HTTPS at `https://laravel-legacy.dev`); its config is not versioned.

## Accounts

| Email | Password | Role in "PHP 8.4 Migration" |
|---|---|---|
| `ana@example.com` | `secret123` | owner |
| `bruno@example.com` | `secret123` | member |
| `carla@example.com` | `secret123` | viewer |

## Verification

```bash
# 153 tests, 514 assertions (in-memory SQLite)
vendor/bin/phpunit --testdox

# 33 HTTP tests against the running app (CSRF, cookies, throttle, TLS, IDOR, CSP)
php artisan lab:verify --base=http://127.0.0.1:8000

# PHP 8.4 blocker inventory
composer why-not php 8.4

# Measures blockers and both suites for /migration (run it before a demo:
# without it the page honestly says "not measured")
php artisan lab:snapshot
```

## Quick map

```
app/Task.php                                   model + scopes (filter, sort, stats)
app/Project.php                                project + roles on the pivot
app/Policies/{Task,Project}Policy.php          composite authorization
app/Observers/TaskObserver.php                 audit trail
app/Flow/{FlowMetrics,Chart}.php               process metrics + server-side SVG
app/Legacy/                                    the SIG billing module (frozen by tests)
app/Http/Controllers/                          dashboard, tasks, projects, billing, account, ...
app/Jobs/ImportTasksCsv.php                    queued import (stream, $tries = 1)
app/Console/Commands/SendDueSoonReminders.php  atomic reminder claim
resources/views/partials/ui.blade.php          design tokens (single source of color)
tailwind.config.js                             Tailwind 1 classes mapped to the tokens
app/Migration/{Overview,Evidence}.php          /migration page + home counts, read from the snapshot
app/Console/Commands/{VerifyHttp,MigrationSnapshot}.php   lab:verify and lab:snapshot
tests/Feature/ · tests/Unit/Legacy/            behavior and attack suite (kernel)
tests/Http/                                    edge suite: real HTTP
.github/workflows/ci.yml                       the pipeline (docs/ci.md)
.github/actions/setup/                         shared PHP 7.2 + Composer setup for every CI job
```

## How a change gets in

Nothing is committed straight to `main`. Every change, however small:

1. a branch cut from `main`, one logical unit per branch;
2. a pull request filled from the template: **What, Why, Verification,
   Rollback**;
3. CI green: lint, kernel suite, edge suite against a running server, and the
   `lab:snapshot` evidence (details in [docs/ci.md](docs/ci.md));
4. squash merge, so `main` reads as one commit per step.

CI also runs every night, with no code change, to catch the environment
rotting under a legacy app, and can be started by hand before a demo.

## Conventions

Everything is in English: code, comments, UI text, tests, seed data, CI and
docs. The only Portuguese left is inside data migrations
(`2019_06_17_...`, `2026_11_12_...`): they match rows by their old stored
values, so those values must appear exactly as they were saved.

Applied migrations are never edited; every rename ships as a new data
migration.

## Kept old on purpose

Laravel 5.8, PHP 7.2, Tailwind 1.9, Alpine 2.8 (with `'unsafe-eval'` in the
CSP), `laravelcollective/html`, `doctrine/dbal` 2.x, `predis/predis` 1.x and
`fzaninotto/faker` (abandoned). The goal is to reproduce the real "before" and
show the migration method, step by step, with a rollback at each one.
