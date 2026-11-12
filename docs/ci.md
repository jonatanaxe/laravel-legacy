# CI/CD

The pipeline is the migration method made executable: **nothing reaches
`main` unless the legacy's behavior is proven again**, on the PHP version it
runs on in production (7.2), and every claim the case makes ("behavior is
frozen", "every step has a rollback", "the numbers are measured") is a job
that runs instead of a sentence that is believed.

Workflow: [`.github/workflows/ci.yml`](../.github/workflows/ci.yml) ·
shared setup: [`.github/actions/setup`](../.github/actions/setup/action.yml).

---

## 1. The pipeline at a glance

```
Static · PHP syntax + composer.lock
   │
   ├── Tests · business rules (kernel) ──┐
   ├── Tests · real HTTP (edge) ─────────┴── Evidence · migration snapshot
   ├── Rollback · every migration on MariaDB
   │
   ├── Report · test coverage
   └── Report · dependency advisories
```

Check names read **`<stage> · <what it proves>`**, so the list of checks on a
pull request tells the story in order: check the code, test it, prove the
rollback, record the evidence, measure.

| Check | Kind | Proves | Artifact |
|---|---|---|---|
| Static · PHP syntax + composer.lock | **gate** | every PHP file parses; the lock matches `composer.json` | – |
| Tests · business rules (kernel) | **gate** | the rules of the app and the frozen legacy module | JUnit + testdox |
| Tests · real HTTP (edge) | **gate** | what only exists over HTTP: CSRF, cookies, throttling, headers, IDOR | JUnit + server log |
| Rollback · every migration on MariaDB | **gate** | every `down()` runs and the schema can be rebuilt | – |
| Evidence · migration snapshot | **gate** | the measured state: PHP 8.4 blockers + both suites' results | `snapshot.json` (90 days) |
| Report · test coverage | report | how much of `app/` the kernel suite executes | HTML + clover |
| Report · dependency advisories | report | known vulnerabilities and abandoned packages | `audit.json` |

**Gates** decide whether a change can merge. **Reports** measure and never
block (why: section 4). Every job writes a short table to the run's
**Summary** tab, so nobody needs to open a log to read the result.

---

## 2. When it runs

| Trigger | Why |
|---|---|
| Pull request (any branch) | The gate: every change is proven before it can merge. |
| Push to `main` | Each merged commit keeps its own complete, green run as proof. |
| Every night, 06:17 UTC (03:17 São Paulo) | A legacy environment rots without code changes: a runner image is retired, a PPA drops an old PHP. The nightly run finds it before the next PR does. |
| Manual (`workflow_dispatch`) | One click before a demo, to show a run from minutes ago. |

Push runs only on `main`: on a PR branch, `pull_request` already covers every
commit, and running both doubled every run.

---

## 3. The checks, one by one

### Static · PHP syntax + composer.lock

- **Runs:** `php -l` on every file under `app database routes tests`;
  `composer validate`.
- **Why it exists:** views, routes and console commands that no test loads
  can still carry a parse error, and only production would find it. A
  `composer.lock` out of sync with `composer.json` installs something nobody
  reviewed.
- **First because:** it takes seconds and every other job depends on code
  that at least parses. A typo fails here, not after five minutes of suites.

### Tests · business rules (kernel)

- **Runs:** `vendor/bin/phpunit` (153 tests) on in-memory SQLite:
  `tests/Unit`, including the 29 characterization tests in
  `tests/Unit/Legacy`, and `tests/Feature`.
- **Why it exists:** it is what "behavior frozen" means. Authorization (four
  layers), mass assignment, concurrency (409), audit trail, CSV import,
  reminders, and the legacy billing module **including its known bugs**,
  pinned on purpose so a migration can't change them silently.
- **Why in-memory SQLite:** the whole suite runs in about two seconds with no
  service to start, so it can run on every push. The edge and rollback jobs
  cover what SQLite can't.
- **Nothing is skipped because it is legacy.** The characterization tests are
  the first thing that has to stay green.
- **Artifact:** `kernel-reports` (JUnit XML and testdox), read later by the
  evidence job.

### Tests · real HTTP (edge)

- **Runs:** seeds a SQLite file, starts `php artisan serve`, then
  `php artisan lab:verify` (33 tests in `tests/Http`).
- **Why it exists:** some protections don't exist inside the framework's test
  kernel. Laravel disables CSRF in feature tests, cookies' `Secure` and
  `HttpOnly` flags only exist on a real response, throttling needs real
  repeated requests. This suite talks HTTP to a running server, like an
  attacker would.
- **Why a separate suite and job:** it needs a server and a seeded database,
  it is slower, and when it fails the cause is usually the environment, not a
  rule. Keeping it apart makes a red check say which layer broke.
- **Artifact:** `edge-reports` (JUnit XML and the server log, for when the
  server itself is the problem).

### Rollback · every migration on MariaDB

- **Runs:** a MariaDB 11.8.9 service; `migrate --seed` (up, with data),
  `migrate:reset` (every `down()`, newest first), a check that nothing is
  left applied, then `migrate` and `db:seed` again.
- **Why it exists:** "every step has a rollback" is the core of the method,
  and before this job nothing executed a single `down()`.
- **Why MariaDB:** it is the production engine, at the same version as the
  local container. Writing this job showed that the `down()` methods never
  worked on SQLite: dropping a column rebuilds the table and loses its
  indexes and foreign keys, and Laravel 5.8 refuses to drop a foreign key
  there at all. Editing applied migrations for an engine that never runs in
  production would be the wrong trade, so the contract is explicit:
  **rollback is guaranteed on MariaDB**; the SQLite databases of development
  and CI are recreated with `migrate:fresh`, never rolled back.

### Evidence · migration snapshot

- **Runs:** after both test jobs are green, downloads their JUnit reports and
  runs `php artisan lab:snapshot --kernel-junit=… --edge-junit=…`, which adds
  the PHP 8.4 blocker inventory (`composer why-not php 8.4`).
- **Why it exists:** the `/migration` page and the home page quote numbers
  (tests, blockers). They read this snapshot instead of hard-coding them,
  because hard-coded numbers drifted (the home page once said 182 tests when
  there were 183).
- **Why it reads the reports instead of running the suites again:** the
  snapshot then quotes **exactly the runs that gated this commit**, and the
  job takes seconds. A missing or unreadable report is recorded as a failed
  suite, never as zero tests that look green.
- **Artifact:** `migration-snapshot`, kept 90 days: the measured state of
  every merged commit.

### Report · test coverage

- **Runs:** the kernel suite with Xdebug; line, method and class coverage of
  `app/` in the summary, the HTML report as an artifact.
- **Why it exists:** it answers "how much of the legacy is frozen?" with a
  number.
- **Why Xdebug:** PHPUnit 7.5 predates PCOV support.
- **Why no threshold:** on a legacy codebase, a minimum percentage rewards
  writing tests for easy code instead of the risky paths. The number is for
  reading and for trends, not for gating.
- The edge suite exercises the same code over HTTP and isn't counted here.

### Report · dependency advisories

- **Runs:** `composer audit --locked`; advisories by severity and abandoned
  packages in the summary, `audit.json` as an artifact.
- **Why it exists:** it answers "what known risks does the legacy carry?"
  from the lock file, not from memory.
- **Why it doesn't fail:** Laravel 5.8 / PHP 7.2 carries known advisories by
  design. They are the risk the migration removes; a gate would be red
  forever and teach everyone to ignore red. Reported, not gated.
- **Why it uses the latest Composer 2:** `composer audit` needs 2.4+, and its
  value is the current advisory database. Every other job uses Composer
  2.2.32, the local container's version.

---

## 4. Decisions and why

| Decision | Why | Where |
|---|---|---|
| CI runs PHP 7.2, not a newer PHP | CI must prove the "before" the app runs on, not a version it doesn't support. The upgrade is a separate, planned step. | `env.PHP_VERSION` |
| Runner `ubuntu-22.04` | Same base as the local container. `ubuntu-20.04` was retired by GitHub in April 2025 and would have queued forever. | every job |
| One composite setup action | PHP, extensions, Composer and cache defined once, so seven jobs can't drift apart. | `.github/actions/setup` |
| Exact versions everywhere | Actions pinned by commit SHA (a tag can be moved to other code; a SHA can't), MariaDB `11.8.9`, Composer `2.2.32`: a run today and a run next month execute the same thing. | workflow, setup action |
| Dependabot for the actions only | It proposes the SHA bumps as PRs, each gated by this pipeline. Composer is left out on purpose: the app's dependencies are frozen as the "before", and changing them is a planned migration step, not an automatic PR. | `.github/dependabot.yml` |
| Gates vs. reports | A gate must be able to turn green. Advisories on Laravel 5.8 and a coverage percentage can't be "fixed" before the migration, so they measure instead of blocking. | report jobs |
| Kernel and edge in parallel, evidence after both | Fast feedback; and the snapshot is only written when both suites passed. | `needs:` |
| Evidence reads JUnit reports | The recorded numbers are those of the gating runs, and the tests don't run twice. | evidence job |
| Rollback tested on MariaDB, not SQLite | Production engine; SQLite can't drop columns or foreign keys without rebuilding tables (section 3). | rollback job |
| Cancel superseded runs on PRs only | On a PR only the latest commit matters. On `main`, every merged commit keeps its complete run. | `concurrency` |
| `timeout-minutes` on every job (10 to 20) | A server that never answers fails in minutes instead of holding a runner for the six-hour default. | every job |
| Read-only token | CI checks; it never writes to the repository. Least privilege. | `permissions` |
| Nightly run | A legacy environment breaks without code changes; find it before a PR does. | `schedule` |
| Artifacts kept 30 days, snapshot 90 | Reports serve the review of a change; the snapshot is the history of the measured state. | upload steps |
| PR template with **Rollback** | The method in one field: a change without a rollback is a gamble, not a step. | `.github/pull_request_template.md` |
| Squash merge, one commit per PR | `main` reads as one commit per step, each with its own green run. | how every PR is merged |

---

## 5. Safety rails

- **Tests can only rebuild the in-memory database.** `phpunit.xml` forces the
  test environment (`<env force="true">`) and `Tests\TestCase` refuses
  `RefreshDatabase` on any other connection. Without it, `lab:snapshot`
  (which loads `.env`) once ran the suite against the real database and
  wiped it.
- **The edge suite fails fast** when the server doesn't answer `/health`,
  with a message about the environment instead of 33 connection errors.
- **Not measured is shown as not measured.** Pages that read the snapshot say
  so when there is none, and a missing report is recorded as failed; they
  never show a zero that looks like a result.

---

## 6. What CI has caught so far

| What | How it showed up | Fix |
|---|---|---|
| `ubuntu-20.04` runner retired | Would have queued forever on the first push | baseline commit |
| An HTTP test searched a task by its old Portuguese title | Passed locally (database seeded before the translation), failed on CI's fresh seed: 2 of 33 edge tests | PR #1 |
| `lab:snapshot` wiped the real database | Both suites "failed" with fewer assertions; the local database was empty afterwards | PR #2 |
| A flaky test depended on a random name | Faker drew "O'Reilly"; Blade escaped the apostrophe and the assertion failed on one of two runs of the same commit | PR #7 |
| Migrations couldn't roll back on SQLite | Writing the rollback job: `no such index` and "SQLite doesn't support dropping foreign keys" | PR #13 (contract: MariaDB) |

Each of these passed on a laptop. That table is the argument for CI.

---

## 7. Reproduce it locally

The same commands, in the same order:

```bash
# Static
find app database routes tests -name '*.php' -print0 | xargs -0 -n1 php -l > /dev/null
composer validate --no-check-publish

# Tests
vendor/bin/phpunit --testdox                                   # kernel
php artisan lab:verify --base=http://127.0.0.1:8000            # edge, server running

# Rollback, on a scratch MariaDB database (never the one with your data)
DB_DATABASE=rollback_check php artisan migrate --seed --force
DB_DATABASE=rollback_check php artisan migrate:reset --force
DB_DATABASE=rollback_check php artisan migrate --seed --force

# Evidence: runs both suites itself when no reports are given
php artisan lab:snapshot
```

---

## 8. Deliberately not here

- **No "next PHP version" job yet.** The upgrade has not started; the stack
  stays on Laravel 5.8 / PHP 7.2 on purpose. When it starts, the first change
  is a non-blocking PHP 7.4 job that shows how many tests break on the next
  step, before any code changes.
- **No deployment.** The app runs locally only; section 9 is how it would.

---

## 9. CD: how deployment would work

The same rules as the migration plan, applied to delivery:

1. **One step per deploy.** Each rung of the ladder
   ([cto-brief](cto-brief.md)) is its own release: runtime first, framework
   second. A deploy never bundles two causes of failure.
2. **Gate = this pipeline.** A release is a commit on `main` whose five gates
   are green; `main` only accepts merges through pull requests with green
   gates.
3. **Artifact, not rebuild.** The image built and tested is the one deployed,
   and the snapshot of that commit travels with it as evidence.
4. **Rollback = the previous deploy.** Old and new app share the same database
   until the next step, so going back is redeploying the previous image with
   no data migration to undo. Schema changes are additive and ship one
   release before the code that needs them; the rollback job proves every
   `down()` exists for when one is needed.
5. **Verify after deploy.** `/health` (database, cache, disk) and
   `php artisan lab:verify --base=<production URL>` against the real
   environment: the same edge suite CI runs.
