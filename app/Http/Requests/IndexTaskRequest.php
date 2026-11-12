<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * List filters come in through the query string and go straight to the DB.
 *
 * Without validation here, `?sort=` becomes an arbitrary ORDER BY and
 * `?status=` becomes unexpected behavior - and a typo can turn into a full
 * table scan. A whitelist solves both.
 */
class IndexTaskRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'status' => 'nullable|in:all,open,done,overdue',
            'q' => 'nullable|string|max:100',
            'sort' => 'nullable|in:recent,due,priority',
        ];
    }

    /**
     * Sanitized values with defaults - the view and the controller read these.
     */
    public function filters()
    {
        $data = $this->validated();

        return [
            'status' => $data['status'] ?? 'all',
            'q' => $data['q'] ?? '',
            // Priority as the default: the first screen answers "what matters
            // now", not "what was created last".
            'sort' => $data['sort'] ?? 'priority',
        ];
    }
}
