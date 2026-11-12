<?php

namespace App\Http\Requests;

use App\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Server-side validation, in one place.
 *
 * Golden rule: browser validation (required, maxlength) is a UX convenience;
 * the server never trusts it.
 */
class StoreTaskRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'title' => 'required|string|min:3|max:120',
            'description' => 'nullable|string|max:2000',
            // Whitelist: the form sends the string, but the server decides the
            // valid set (see Task::PRIORITIES).
            'priority' => ['nullable', Rule::in(Task::PRIORITIES)],
            'due_date' => 'nullable|date_format:Y-m-d',
            'done' => 'nullable|boolean',
            // Workflow stage. Server-side whitelist: the form sends the
            // string, but a value outside Task::STATUSES never reaches the DB.
            'status' => ['nullable', Rule::in(Task::STATUSES)],
            // exists rejects a missing project; the user's ROLE in the project
            // is checked later by the policy (ProjectPolicy::createTask).
            'project_id' => 'nullable|integer|exists:projects,id',
        ];
    }

    /**
     * Server-side defaults, so an optional field doesn't become NULL in a
     * NOT NULL column (priority) when the client sends nothing.
     */
    public function validatedDefaults()
    {
        $data = $this->validated();
        $data['priority'] = $data['priority'] ?? 'normal';

        // `status` is the source of the stage; `done` is the mirror that older
        // paths (CSV, API) still send. When both arrive, the stage wins -
        // otherwise the array order would change the result.
        if (array_key_exists('status', $data) && $data['status'] !== null) {
            unset($data['done']);
        }

        return $data;
    }
}
