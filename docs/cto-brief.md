# Engineering brief: Atlas ERP

Supporting document for the CTO conversation. Numbers measured on this machine.

## In one sentence

Atlas ERP is the system of a fictional client (Atlas Services): a Laravel 5.8 /
PHP 7.2 application that **is not a tutorial CRUD**. It has a dashboard,
projects with roles, authorized attachments, a reminder queue, CSV import with
a per-line report, an audit trail, a token API, a health endpoint, CI and a
billing module inherited from 2009. It is the controlled rehearsal for a
migration to PHP 8.4.

## What exists

**Product**
- Dashboard after login: my week, projects, billing this month, recent activity.
- Task list with filters, search and sorting, always in the database.
- Projects with roles (owner/member/viewer), an overview tab and a schedule
  (Gantt) tab.
- Attachments on a private disk, downloaded through an authorized route.
- Reminders through a queue + scheduler, with an inbox.
- CSV import processed on a queue, with an error report per line and a
  downloadable template.
- Billing screen for the legacy SIG module: correct summary computed by new
  code, next to the old report shown unchanged (bug included).
- Account screen: profile, projects and the API token (masked, rotatable).
- Sidebar navigation, mobile drawer and an alternate PALO IT theme that only
  swaps the color tokens.

**Engineering**
- Optimistic locking: two tabs on the same task give a **409**.
- Audit trail per task (`task_events`) written by an observer.
- Read-only JSON API with a token and rate limiting.
- `/health` with database, cache and disk checks; 200/503.
- CI on PHP 7.2: `php -l`, `composer validate`, PHPUnit and the HTTP suite.
- Reproducible setup: `php artisan migrate:fresh --seed`.

**Legacy, on purpose**
- Laravel 5.8, PHP 7.2, Tailwind 1.9, Alpine 2.8, PHPUnit 7.5.
- `app/Legacy`: 26 planted problems that only break on future versions, each
  pinned by a characterization test. Answer key: `docs/legacy-answer-key.md`.

**Security**
- Four layers (`auth` middleware, `authorize()`, policy, query scope).
- Security headers, hardened session cookie, HSTS over HTTPS, CSP without
  inline scripts.
- **183 tests**: 150 in the kernel (509 assertions) and 33 at the edge against
  real HTTP: CSRF, cookies, throttling, headers, TLS and IDOR between sessions.

## Decisions and why

| Decision | Why | Where |
|---|---|---|
| Laravel 5.8 + PHP 7.2 | reproduce the real "before", not an ideal example | container |
| Filtering/sorting in SQL | load-then-filter works with 30 rows and falls over with 30k | `app/Task.php` scopes |
| `CASE WHEN` for priority order | `'high' < 'low'` alphabetically gives the wrong order | `Task::scopeSorted` |
| Summary in one `selectRaw` | four `COUNT`s would be four round-trips | `TaskController::stats` |
| `$fillable` without `user_id`/`project_id` | the server decides owner and project | `app/Task.php` |
| `===` in the policy + `integer` cast | strict comparison; cast guarantees the type on any driver | `TaskPolicy`, `Task` |
| The project role decides, not "who created it" | otherwise revoking access would be theater | `TaskPolicy` |
| Observer for auditing | covers web, API and console | `TaskObserver` |
| `version` + transaction on update | the last one to save doesn't silently win | `TaskController@update` |
| `/health` outside the `web` group | a 5 s monitor must not create sessions | `RouteServiceProvider` |
| Atomic claim on `reminded_at` | idempotent; check-then-act would race between workers | `SendDueSoonReminders` |
| Import on a queue, `$tries = 1` | a retry would **duplicate** rows | `ImportTasksCsv` |
| New code next to the legacy | the Billing screen doesn't touch frozen code | `BillingController` |
| Tailwind 1 + Alpine 2 (2020 era) | the front end is legacy too, so its upgrade is part of the case | `tailwind.config.js` |
| Tokens as the only source of color | a whole rebrand is one CSS block | `partials/ui.blade.php` |
| Applied migrations are never edited | a new data migration carries each rename | `database/migrations` |

## What is proven (and how to reproduce it)

```bash
# 150 tests, 509 assertions, about 2 s (in-memory SQLite)
madock cli 'vendor/bin/phpunit --testdox'

# 33 HTTP tests against the live environment
madock cli 'php artisan lab:verify'
```

| Check | Result |
|---|---|
| POST without `_token` | 419 |
| Non-owner on someone's personal task | 403 |
| Project viewer creating/editing | 403 |
| Two PUTs with the same `version` | second one gets 409 |
| Third party downloading an attachment | 403 |
| Disguised `.php` upload | rejected by content |
| Reminder command run twice | 1 notification |
| `/api/tasks` without a token | 401, JSON even without `Accept` |
| 6th login attempt | 429 |
| CSP | `script-src 'self' 'unsafe-eval'`, nothing else |

Real findings the process caught along the way:

1. `user_id` arrived as a string on SQLite and the strict policy gave the owner
   a 403. Fixed with a model cast.
2. CSRF can't be tested in a feature test (Laravel disables it there), so the
   419 proof lives in the HTTP suite.
3. The API returned 302 to clients without `Accept: application/json`.
4. A flaky test: the factory randomized `done`. Explicit default.
5. A broken FK from a column type mismatch that SQLite silently ignored.
6. Date comparison as a string on SQLite hid tomorrow's deadline. `whereDate`
   and `DATE()` fix it on both drivers.
7. `forceDelete` fired `deleted` twice in the trail.
8. The schedule page used Tailwind classes before Tailwind was installed, had
   `onclick` handlers blocked by the CSP, and drew old tasks in the future
   (unsigned `diffInDays`).
9. The migration overview page existed with no route pointing to it.

## Known risks (on purpose)

- **PHP 7.2 has been out of support since Nov 2020**: no security fixes.
- **9 packages** block PHP 8.4 (`composer why-not php 8.4`).
- Two abandoned dependencies: `fzaninotto/faker`, `laravelcollective/html`
  (the latter is unused and can simply be removed).
- **Plain-text API token**: fine for a demo; production needs Sanctum-style
  hashing.
- **`'unsafe-eval'` in the CSP** because of Alpine 2; removed by the Alpine 3
  CSP build.
- The queue runs in `sync` locally; production needs a real worker.
- The scheduler depends on cron, which is infrastructure.
- Attachments live on the app's disk; multiple nodes need shared storage.

## Migration plan (steps with rollback)

```
L5.8 / PHP 7.2 → L6-8 / 7.4 → L8 / 8.0 → L9 / 8.1 → L10 / 8.2 → L11-13 / 8.4
```

1. Freeze behavior with tests (done).
2. Clear abandoned packages first (faker → fakerphp/faker; remove
   laravelcollective/html).
3. One step per deploy; runtime first, framework second. Laravel 8 is the
   bridge between PHP 7.4 and 8.x.
4. PHP 8.1 is where legacy code usually dies: `ReturnTypeWillChange`, `null`
   passed to internal functions.
5. Target Laravel 13: Laravel 11 lost security fixes in Mar 2026 and 12 loses
   them in Feb 2027.
6. Front end in the same plan: Tailwind 1 → 4, Alpine 2 → 3 (CSP build), which
   lets the CSP drop `'unsafe-eval'`.

## Demo runbook (6 minutes)

Before: `php artisan lab:snapshot`, so `/migration` shows measured blockers
and suites instead of "not measured".

1. **Presentation (`/`)**: concepts, the ladder, the evidence table.
2. **Sign in as Ana**: the dashboard (overdue, my week, recent activity).
3. **Tasks**: filter "Overdue", sort by due date, open a task (history, details).
4. **Two tabs on the same task**: the second save gets **409**.
5. **Projects → PHP 8.4 Migration**: overview, then the Schedule tab.
6. **Billing**: the legacy report shows drafts as "Issued"; the new summary next
   to it is correct. That is code frozen by tests, not a slip.
7. **Theme switch**: Atlas → PALO IT. One CSS block, no screen touched.
8. **Terminal**: `vendor/bin/phpunit --testdox`, `php artisan lab:verify`,
   `composer why-not php 8.4`.
