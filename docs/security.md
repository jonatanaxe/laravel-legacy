# Atlas ERP: security practices

Laravel **5.8.38** / PHP **7.2.34**, the "before" of a real migration.

## What the app does

Per-user tasks with projects and roles, attachments, queued reminders, CSV
import, a read-only token API and the legacy SIG billing module. Login by
email/password (`make:auth`).

Key files:

```
app/Task.php                                       model
app/Project.php                                    model + roles (owner/member/viewer)
app/TaskAttachment.php                             attachment model
app/TaskImport.php                                 import report model
app/Policies/TaskPolicy.php                        composite authorization
app/Policies/ProjectPolicy.php                     role-based authorization
app/Http/Requests/StoreTaskRequest.php             validation
app/Http/Controllers/TaskController.php            controller (auth middleware)
app/Http/Controllers/ProjectController.php         projects and members
app/Http/Controllers/TaskAttachmentController.php  authorized upload/download
app/Http/Controllers/NotificationController.php    reminder inbox
app/Http/Controllers/TaskImportController.php      upload + report
app/Http/Controllers/AccountController.php         profile and API token
app/Jobs/ImportTasksCsv.php                        queued processing
app/Console/Commands/SendDueSoonReminders.php      atomic reminder claim
app/Http/Middleware/SecurityHeaders.php            CSP, HSTS and friends
```

## Front end: one token file

Every color, space and size lives in `resources/views/partials/ui.blade.php`
(CSS variables on `:root` plus the legacy components). Tailwind 1.9 (compiled
with `npm run css:build`) only gives those tokens class names. The alternate
PALO IT theme redefines the color tokens and nothing else.

### Mixed content over HTTPS (two fixes)

1. `AppServiceProvider::boot()` calls `URL::forceScheme('https')` when
   `APP_URL` is https, which fixes `asset()` and `route()`.
2. `TrustProxies::$proxies = '*'`: TLS terminates at madock's proxy and PHP
   receives http with `X-Forwarded-Proto: https`. Without trusting the proxy,
   `$request->url()` answers `http` and the **paginator** (which builds the
   path from the `Request`, not the `UrlGenerator`) produces mixed links.

## Protections and where they live

| Risk | Protection | Where |
|---|---|---|
| **CSRF** | `@csrf` on every form; POST + `@method` for update/delete | `_form.blade.php`, views |
| **Mass assignment** | `$fillable` without `user_id`/`project_id`; the owner comes from the server | `app/Task.php`, `TaskController@store` |
| **IDOR** | Policy + `$this->authorize()` on every action; queries scoped to the owner | `TaskPolicy.php`, controllers |
| **Role escalation** | Project policy: a `viewer` can't write, even on a task they created | `ProjectPolicy`, `TaskPolicy` |
| **XSS** | `{{ }}` (escaped) on every output; never `{!! !!}` on user data | views |
| **SQLi** | Eloquent/relationships with bindings; no user input concatenated into SQL | controllers, scopes |
| **Validation** | `FormRequest` on the server, not just `required` in HTML | `app/Http/Requests` |
| **Session** | `auth` on routes and in the controller (defense in depth) | `routes/web.php` |
| **Executable upload** | `mimes:` checks the content; private disk; server-generated name | `TaskAttachmentController` |
| **Path traversal** | `basename()` + a random name on disk; the original is only a label | `TaskAttachmentController@store` |
| **Hostile CSV** | `user_id`/`project_id` from the file are ignored; a bad line doesn't sink the rest | `app/Jobs/ImportTasksCsv.php` |
| **Concurrency** | `version` + transaction + `lockForUpdate()` give a 409 | `TaskController@update` |
| **Duplicate reminder** | `UPDATE ... WHERE reminded_at IS NULL` (atomic claim) | `SendDueSoonReminders` |
| **Script injection** | CSP `script-src 'self' 'unsafe-eval'`; no inline handlers | `SecurityHeaders` |

### Known debt in the CSP

`'unsafe-eval'` is in `script-src` because Alpine 2 evaluates its HTML
expressions with `new Function()`. Inline scripts are still blocked, and a
test (`HeadersTest::test_unsafe_eval_is_the_only_script_exception`) pins it as
the only exception. It goes away with the Alpine 3 CSP build during the
upgrade.

## How to attack it (and see the defense)

No session: redirects to `/login`.

```bash
curl -sk -o /dev/null -w "%{http_code} -> %{redirect_url}\n" https://laravel-legacy.dev/tasks
```

**CSRF**: a POST without `_token` returns **419**.

```bash
curl -sk -o /dev/null -w "%{http_code}\n" -d "title=hack" https://laravel-legacy.dev/tasks
```

**IDOR**: logged in as Bruno, opening one of Ana's personal tasks gives **403**.

**Mass assignment**: `POST /tasks` with an extra `user_id=1`. The field is
dropped (it isn't in `$fillable`) and the task belongs to the session user.

**XSS**: create a task titled `<script>alert(1)</script>`. It shows as text,
because Blade escapes it.

**Project escalation**: Bruno is a member of Ana's project. He sees and edits
the project's tasks, but **not** her personal ones, and he can't delete the
project or manage members (403). Sending another project's `project_id` in
the form also gives 403: the policy blocks it before saving.

**Changing a role is real**: demoting a member to viewer removes edit access
**even on the tasks they created**
(`ProjectAccessTest::test_member_demoted_to_viewer_loses_edit_on_own_task`).

**Someone else's attachment**: `/attachments/{id}/download` with another
user's id gives 403 (authorization is on the task, not on "is logged in"). A
disguised `shell.php` is rejected by its content (`mimes:`), and the file on
disk has a generated name, so `../../etc/passwd.txt` only survives as the
label `passwd.txt`.

**Hostile import**: a CSV with `user_id` or `project_id` columns changes
neither the owner nor the project. Another user's import report gives 404.

**Two tabs on the same task**: the second save with a stale `version` gets
**409** and the first value is kept.

## Legacy dependencies (the "before" of the upgrade)

Installed on purpose:

| Package | Version | Status |
|---|---|---|
| `laravelcollective/html` | 5.8.1 | **abandoned**, and unused here: remove it |
| `predis/predis` | 1.1.10 | two majors behind |
| `doctrine/dbal` | 2.x | 3.x is current |
| `fzaninotto/faker` | 1.9.2 | abandoned (became `fakerphp/faker`) |

## Upgrade inventory (`composer why-not php 8.4`)

Today **9 packages** block it:

```
laravel/laravel                 requires php (^7.1.3)
beyondcode/laravel-dump-server  requires php (^7.1)
fzaninotto/faker                requires php (^5.3.3 || ^7.0)
laravel/framework               requires php (^7.1.3)
phar-io/manifest                requires php (^5.6 || ^7.0)
phar-io/version                 requires php (^5.6 || ^7.0)
phpunit/php-code-coverage       requires php (^7.1)
phpunit/phpunit                 requires php (^7.1)
sebastian/global-state          requires php (^7.0)
```

Steps (there is no direct jump):

```
L5.8/PHP7.2 → L6-8/PHP7.4 → L8/PHP8.0 → L9/PHP8.1 → L10/PHP8.2 → L11-13/PHP8.4
```

## API, audit trail and operations

### API token (read only)

- `token` guard in `config/auth.php` (`hash => false`, on purpose: a visible
  demo token). `users.api_token` is `unique` and `nullable`.
- `Authorization: Bearer <token>` header. The token **never** comes back in a
  body: `api_token` is in `$hidden`. The Account screen shows it masked and can
  rotate it (POST + CSRF + throttle).
- `ForceJsonResponse` on the `api` group: `/api/*` answers 401/422 as JSON even
  when the client forgets `Accept: application/json`. Without it the framework
  returns a 302 to `/login`, which an API consumer reads as network success.
- Authorization uses `authorizeForUser($request->user('api'), ...)`, because
  the normal `$this->authorize()` would resolve the `web` guard, which doesn't
  exist on a token request. Same policy, so IDOR is blocked the same way.

```bash
TOKEN=$(printf 'ana@example.com|lab-migration' | sha256sum | cut -d' ' -f1)
curl -sk -H "Authorization: Bearer $TOKEN" https://laravel-legacy.dev/api/tasks
```

### Audit trail

- `task_events` table: `task_id`, `user_id`, `action`, `meta` (JSON),
  `created_at`. No cascading FK: if the task is deleted, the event stays as
  proof that it existed.
- `TaskObserver` covers controllers, the API and the console. If the logging
  lived in a controller, `artisan` would be left out.
- `updated` only records when `isDirty()`; completing and reopening have their
  own actions (`completed`/`reopened`).
- `meta` keeps the diff: `{"changed":{"title":{"from":"...","to":"..."}}}`.
  Older events with non-English diff keys were converted by a data migration
  (`2026_11_12_000001_...`), so the reader only knows `from`/`to`.

### /health

- Checks the database (`select 1`), the cache (write, read, delete) and writes
  to `storage/framework`. 200 when everything passes, 503 when something
  fails, which is the code an orchestrator understands.
- Lives **outside** the `web` group: a monitor hitting it every 5 s must not
  create a session per call.
- Exposes no `APP_KEY`, DB password or connection string.

### Attachments

- Files on the `local` disk (`storage/app/attachments/{task_id}`), **outside**
  the document root. Downloads go through `attachments/{id}/download`, which
  calls `authorize('view', $attachment->task)`.
- `mimes:` validates the **content**: a `.php` renamed to `.txt` is still
  rejected. `max:5120` (5 MB) and a cap of 10 attachments per task.
- The name on disk comes from `store()`; the original name is sanitized and
  only used in the download header.

### Reminders (queue + scheduler)

- `TaskDueSoon implements ShouldQueue`, `database` channel: the reminder is
  stored in the app (with `read_at`), not lost in an email.
- Idempotency through an atomic claim:
  `UPDATE tasks SET reminded_at = now() WHERE id = ? AND reminded_at IS NULL`.
  Whoever changed 1 row sends; two workers at once don't duplicate it.
- The claim uses the query builder, so it creates **no** audit event.
- The `/notifications` inbox always starts from
  `auth()->user()->notifications()`: someone else's id becomes 404, not 403.

### CSV import

- The upload goes to a private disk under a generated name; the request only
  creates the record and dispatches `ImportTasksCsv`. The file is deleted after
  processing.
- `$tries = 1`: an import is not idempotent, and a retry would duplicate rows.
- Streamed reading with `fgetcsv`, capped at 5000 lines and 10 MB.
- A bad line doesn't sink the file: it goes into the report with its line
  number, and the valid ones are saved.

## Test accounts

| Email | Password | Role in "PHP 8.4 Migration" |
|---|---|---|
| `ana@example.com` | `secret123` | owner |
| `bruno@example.com` | `secret123` | member |
| `carla@example.com` | `secret123` | viewer |

Tasks without a project stay personal: only the owner sees them.
