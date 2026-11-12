<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TaskImport extends Model
{
    const STATUSES = ['pending', 'processing', 'done', 'failed'];

    /** File size ceiling: above this the problem is the process, not the app. */
    const MAX_ROWS = 5000;

    /** How many errors are stored: a useful report without a giant row. */
    const MAX_ERRORS = 50;

    /**
     * user_id, status and counters belong to the server. None of it comes
     * from the form - otherwise a POST could forge "500 imported".
     */
    protected $fillable = ['original_name'];

    protected $casts = [
        'user_id' => 'integer',
        'total_rows' => 'integer',
        'imported_rows' => 'integer',
        'failed_rows' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function errorsList()
    {
        $decoded = $this->errors ? json_decode($this->errors, true) : [];

        return is_array($decoded) ? $decoded : [];
    }

    public function statusLabel()
    {
        $labels = [
            'pending' => 'queued',
            'processing' => 'processing',
            'done' => 'done',
            'failed' => 'failed',
        ];

        return $labels[$this->status] ?? $this->status;
    }

    public function statusBadge()
    {
        $map = [
            'pending' => 'badge-muted',
            'processing' => 'badge-info',
            'done' => 'badge-ok',
            'failed' => 'badge-danger',
        ];

        return $map[$this->status] ?? 'badge-muted';
    }
}
