<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use SoftDeletes;

    /**
     * Accepted priorities. The list lives here, not scattered across views
     * and validation, so a new value can't become a silent bug.
     */
    const PRIORITIES = ['low', 'normal', 'high'];

    /**
     * Workflow stages, in the order a task moves through them.
     *
     * The order IS the workflow: backlog -> doing -> review -> done. The CFD
     * (one band per stage) and the time-in-stage numbers come from it. If
     * stages ever become configurable this constant becomes a table - for
     * now it is enough and fits on one line.
     */
    const STATUSES = ['backlog', 'doing', 'review', 'done'];

    /**
     * Mass assignment: only fields the user may send.
     * 'user_id' and 'project_id' are NOT here on purpose - otherwise a POST
     * with user_id=1 would create a task in someone else's account, and
     * project_id=7 would drop it into a project the user doesn't belong to.
     */
    protected $fillable = ['title', 'description', 'priority', 'due_date', 'done', 'status'];

    protected $casts = [
        'done' => 'boolean',
        // Without this, a driver that returns integers as strings (sqlite)
        // makes the policy's strict comparison fail and the owner gets a 403
        // on their own task. Casting in the model fixes the type everywhere.
        'user_id' => 'integer',
        // Optimistic-lock version: an integer on every driver (some sqlite
        // versions return a string and the strict comparison would fail).
        'version' => 'integer',
        // Same reason: the policy compares it with project ids.
        'project_id' => 'integer',
        // Carbon: views format and compare deadlines without manual parsing.
        'due_date' => 'date',
        'reminded_at' => 'datetime',
    ];

    /**
     * `status` is the source of truth; `done` is its mirror.
     *
     * Two writers coexist: the new form sends `status`, and older code (CSV
     * import, tests, API) sends `done`. Instead of scattering "if status is
     * done then done = true" across controllers, views and jobs, the
     * translation lives in the model - one place, and no path can write the
     * two out of sync.
     */
    public function setStatusAttribute($value)
    {
        // Whitelist here too: validation covers the form, but the model
        // protects seeders, jobs and tinker from the same mistake.
        $status = in_array($value, self::STATUSES, true) ? $value : 'backlog';

        $this->attributes['status'] = $status;
        $this->attributes['done'] = $status === 'done';
    }

    public function setDoneAttribute($value)
    {
        $done = filter_var($value, FILTER_VALIDATE_BOOLEAN);

        $this->attributes['done'] = $done;

        if ($done) {
            $this->attributes['status'] = 'done';

            return;
        }

        // Unchecking "done" does not send a task in review back to the
        // backlog: only a task that was done leaves 'done'.
        $current = isset($this->attributes['status']) ? $this->attributes['status'] : 'backlog';
        $this->attributes['status'] = $current === 'done' ? 'backlog' : $current;
    }

    public static function statusLabels()
    {
        return [
            'backlog' => 'backlog',
            'doing' => 'in progress',
            'review' => 'in review',
            'done' => 'done',
        ];
    }

    /**
     * Label used next to a count ("8 in backlog", "17 done"). The badge label
     * on its own reads wrong after a number.
     */
    public static function statusLabelsPlural()
    {
        return [
            'backlog' => 'in backlog',
            'doing' => 'in progress',
            'review' => 'in review',
            'done' => 'done',
        ];
    }

    public function statusLabel()
    {
        $labels = self::statusLabels();

        return $labels[$this->status] ?? $this->status;
    }

    public function statusBadge()
    {
        $map = [
            'backlog' => 'badge-muted',
            'doing' => 'badge-info',
            'review' => 'badge-warn',
            'done' => 'badge-ok',
        ];

        return $map[$this->status] ?? 'badge-muted';
    }

    /**
     * Position of the stage in the workflow (0..3). Used for ordering and to
     * tell whether the audit trail shows progress or a step back.
     */
    public function statusRank()
    {
        $rank = array_search($this->status, self::STATUSES, true);

        return $rank === false ? 0 : $rank;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function events()
    {
        return $this->hasMany(TaskEvent::class)->latest('id');
    }

    public function attachments()
    {
        return $this->hasMany(TaskAttachment::class)->latest('id');
    }

    /* ---------------------------------------------------------------------
     | Query scopes
     |
     | Filtering and sorting happen in the database. Loading everything and
     | filtering in PHP works with 30 rows and takes the app down with 30k.
     * ------------------------------------------------------------------- */

    /* ---------------------------------------------------------------------
     | Visibility scope
     |
     | The task list is not "user_id = me" anymore: it is "my personal tasks +
     | tasks of the projects where I have a role". One query with subselects -
     | no hydrating everything and filtering in PHP, and no trusting a
     | ?user_id= from the query string.
     * ------------------------------------------------------------------- */

    public function scopeVisibleTo($query, User $user)
    {
        return $query->where(function ($q) use ($user) {
            $q->where('tasks.user_id', $user->id)
                // member of the project (pivot)
                ->orWhereIn('tasks.project_id', function ($sub) use ($user) {
                    $sub->select('project_id')
                        ->from('project_user')
                        ->where('user_id', $user->id);
                })
                // project owner even without a pivot row (legacy data)
                ->orWhereIn('tasks.project_id', function ($sub) use ($user) {
                    $sub->select('id')
                        ->from('projects')
                        ->where('user_id', $user->id);
                });
        });
    }

    /**
     * List *state* filter (open/done/overdue), which is not the `status`
     * column. Named `state` on purpose: `->status($f)` would read as if it
     * filtered by workflow stage.
     */
    public function scopeState($query, $status)
    {
        if ($status === 'open') {
            return $query->where('done', false);
        }

        if ($status === 'done') {
            return $query->where('done', true);
        }

        if ($status === 'overdue') {
            return $query->where('done', false)
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', now()->toDateString());
        }

        return $query; // 'all'
    }

    public function scopeSearch($query, $term)
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        // Escaping % and _ keeps "100%" from becoming a wildcard.
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term) . '%';

        return $query->where(function ($q) use ($like) {
            $q->where('title', 'like', $like)
                ->orWhere('description', 'like', $like);
        });
    }

    public function scopeSorted($query, $sort)
    {
        if ($sort === 'due') {
            // NULLs last: a task without a deadline must not top the list.
            return $query->orderByRaw('due_date IS NULL')
                ->orderBy('due_date')
                ->orderByDesc('id');
        }

        if ($sort === 'priority') {
            // CASE because 'high' < 'low' alphabetically - sorting the string
            // gives the wrong order. The CASE turns the label into a weight.
            return $query->orderByRaw(
                "CASE priority WHEN 'high' THEN 0 WHEN 'normal' THEN 1 WHEN 'low' THEN 2 ELSE 3 END"
            )->orderByDesc('id');
        }

        return $query->latest('id'); // 'recent'
    }

    /* ---------------------------------------------------------------------
     | Presentation
     * ------------------------------------------------------------------- */

    public function isOverdue()
    {
        return !$this->done
            && $this->due_date !== null
            && $this->due_date->isPast()
            && !$this->due_date->isToday();
    }

    public function priorityLabel()
    {
        $labels = ['low' => 'low', 'normal' => 'normal', 'high' => 'high'];

        return $labels[$this->priority] ?? $this->priority;
    }

    public function priorityBadge()
    {
        $map = ['low' => 'badge-muted', 'normal' => 'badge-info', 'high' => 'badge-warn'];

        return $map[$this->priority] ?? 'badge-muted';
    }
}
