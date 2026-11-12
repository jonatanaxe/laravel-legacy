<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * Audit event of a task.
 *
 * Its own model (not a column on tasks) because the history grows without
 * limit and must not bloat the main row. `meta` is JSON text: new fields in
 * the diff don't need a migration.
 */
class TaskEvent extends Model
{
    const UPDATED_AT = null; // only created_at exists

    protected $fillable = ['task_id', 'user_id', 'action', 'meta'];

    protected $casts = [
        'task_id' => 'integer',
        'user_id' => 'integer',
    ];

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function metaArray()
    {
        $decoded = json_decode((string) $this->meta, true);

        return is_array($decoded) ? $decoded : [];
    }

    /** Old value of a diff entry. */
    public static function diffFrom(array $diff)
    {
        return $diff['from'] ?? null;
    }

    /** New value of a diff entry. */
    public static function diffTo(array $diff)
    {
        return $diff['to'] ?? null;
    }

    /**
     * Sentence for each action. Static because the project timeline builds
     * its narrative from plain arrays (no model hydrated per row).
     */
    public static function labels()
    {
        return [
            'created' => 'created the task',
            'updated' => 'edited the task',
            'completed' => 'completed the task',
            'reopened' => 'reopened the task',
            'status_changed' => 'moved the task to another stage',
            'deleted' => 'deleted the task',
            'restored' => 'restored the task',
            'force_deleted' => 'permanently deleted the task',
        ];
    }

    public function label()
    {
        $labels = self::labels();

        return $labels[$this->action] ?? $this->action;
    }
}
