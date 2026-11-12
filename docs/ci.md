# CI/CD

The pipeline is the method made executable: nothing reaches `main` unless the
behavior of the legacy is proven again, on the same PHP version it runs on in
production. Workflow: `.github/workflows/ci.yml`.

## When it runs

- Every push to any branch and every pull request.
- **Every night** (06:17 UTC), with no code change. In a legacy app the
  environment rots on its own: a runner image is retired, a PPA drops an old
  PHP. The nightly run finds out before the next PR does.
- **On demand** from the Actions tab (`workflow_dispatch`), e.g. right before
  a demo.
- A new push to the same branch cancels the run still in progress
  (`concurrency`): only the latest commit matters.
- Every job has a `timeout-minutes` (10 to 20): a server that never comes up
  fails in minutes instead of holding a runner for six hours.
- Read-only token (`permissions: contents: read`): CI checks, it never writes
  to the repository.

## Jobs

```
lint ──┬── kernel tests ──┐
       └── http (edge) ───┴── evidence (lab:snapshot)
```

| Job | What it proves | How | Artifact |
|---|---|---|---|
| **lint** | Every PHP file parses, including views, routes and commands no test loads; `composer.lock` matches `composer.json` | `php -l` on `app database routes tests`, `composer validate` | none |
| **kernel tests** | The business rules: authorization, mass assignment, concurrency (409), audit trail, CSV import, reminders, the frozen legacy billing module | `vendor/bin/phpunit` on in-memory SQLite | `kernel-reports`: JUnit XML + testdox |
| **http (edge) tests** | What only exists over real HTTP: CSRF (419), cookies, throttling (429), security headers, IDOR between sessions | seeds a SQLite file, starts `php artisan serve`, runs `php artisan lab:verify` | `edge-reports`: JUnit XML + server log |
| **evidence** | The measured state of the migration: PHP 8.4 blockers (`composer why-not php 8.4`) and both suites, in one file | `php artisan lab:snapshot` | `migration-snapshot`: `snapshot.json`, kept 90 days |

Every test in the repository runs on every push; nothing is skipped because
the code is legacy. That is the point: the characterization tests in
`tests/Unit/Legacy` freeze what the old billing module does today, so they
are the first thing that has to stay green.

| Directory | Suite | Job |
|---|---|---|
| `tests/Unit` (incl. `tests/Unit/Legacy`) | `phpunit.xml` → Unit | kernel tests |
| `tests/Feature` | `phpunit.xml` → Feature | kernel tests |
| `tests/Http` | `phpunit-http.xml` → HTTP | http (edge) tests |

`kernel` and `edge` run in parallel after `lint`. `evidence` only runs when
both are green, so a snapshot never records a broken state as "measured".

Each job writes a short table to the run's **Summary** tab (tests,
assertions, failures, blockers), so a reviewer doesn't need to open the logs.

### Shared setup

`.github/actions/setup` is a composite action used by every job: PHP from
`shivammathur/setup-php` (7.2, the container's version), the extensions the
suites need, a Composer cache keyed on `composer.lock`, and `composer install`.
One definition, so the four jobs can't drift apart.

The runner is `ubuntu-22.04`, the same base as the local container.
`ubuntu-20.04` runners were retired by GitHub in April 2025.

### Supply chain

Every third-party action is pinned to a **commit SHA**, with the version in a
comment (`actions/checkout@11d5960… # v4.4.0`). A tag can be moved to other
code; a SHA can't. Dependabot (`.github/dependabot.yml`) proposes updates to
those pins weekly, each as a PR that goes through this same pipeline.

Dependabot covers **only** the actions. The app's Composer dependencies are
frozen on purpose (the "before" of the migration); changing them is a planned
step, not an automatic PR.

### Pull requests

`.github/pull_request_template.md` asks every PR for **What, Why,
Verification and Rollback**. The last section is the method in one line: a
change without a rollback is a gamble, not a step.

## Reproduce it locally

The same commands, in the same order:

```bash
find app database routes tests -name '*.php' -print0 | xargs -0 -n1 php -l > /dev/null
composer validate --no-check-publish
vendor/bin/phpunit --testdox                          # kernel
php artisan lab:verify --base=http://127.0.0.1:8000   # edge, server running
php artisan lab:snapshot                              # evidence
```

## Safety rails

- **Tests can only rebuild the in-memory database.** `phpunit.xml` forces the
  test environment (`<env force="true">`), and `Tests\TestCase` refuses
  `RefreshDatabase` on any connection other than SQLite `:memory:`. Without
  this, `lab:snapshot` (which loads `.env`) once ran the suite against the
  real database and wiped it.
- **The edge suite fails fast** when the server doesn't answer `/health`, with
  a message about the environment instead of 33 connection errors.
- **Not measured is shown as not measured.** Pages that read the snapshot say
  so when there is none; they never show a zero that looks like a result.

## What CI has caught so far

| What | How it showed up | Fix |
|---|---|---|
| An HTTP test searched a task by its old Portuguese title | Passed locally (database seeded before the translation), failed on CI's fresh seed: 2 of 33 edge tests | PR #1 |
| `lab:snapshot` wiped the real database | Both suites "failed" with fewer assertions; the local database was empty afterwards | PR #2 |
| `ubuntu-20.04` runner retired | Would have queued forever on the first push | baseline commit |

These are the arguments for CI in one table: each one passed on a laptop.

## Deliberately not here

- **No "next PHP version" job yet.** The upgrade has not started; the stack
  stays on Laravel 5.8 / PHP 7.2 on purpose. When it starts, the first change
  is a non-blocking PHP 7.4 job that shows how many tests break on the next
  step, before any code changes.
- **No deployment.** The app runs locally only.

## CD: how deployment would work

The same rules as the migration plan, applied to delivery:

1. **One step per deploy.** Each rung of the ladder (`docs/cto-brief.md`) is
   its own release: runtime first, framework second. A deploy never bundles
   two causes of failure.
2. **Gate = this pipeline.** A release is a commit on `main` whose four jobs
   are green, and `main` only accepts merges with green CI.
3. **Artifact, not rebuild.** The image built and tested is the one deployed;
   the snapshot of that commit travels with it as evidence.
4. **Rollback = the previous deploy.** Old and new app share the same database
   until the next step, so going back is redeploying the previous image, with
   no data migration to undo. Schema changes are additive and ship one release
   before the code that needs them.
5. **Verify after deploy.** `/health` (database, cache, disk) and
   `php artisan lab:verify --base=<production URL>` against the real
   environment, the same edge suite CI runs.
