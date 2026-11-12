<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TaskAttachment extends Model
{
    /**
     * path, size and mime are set by the server from the file itself.
     * None of them may come from the form - so they stay out of $fillable,
     * along with task_id and user_id.
     */
    protected $fillable = ['original_name'];

    protected $casts = [
        'task_id' => 'integer',
        'user_id' => 'integer',
        'size' => 'integer',
    ];

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function humanSize()
    {
        if ($this->size >= 1024 * 1024) {
            return round($this->size / 1024 / 1024, 1) . ' MB';
        }

        return max(1, round($this->size / 1024)) . ' KB';
    }
}
