<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TaskResource;
use App\Task;
use Illuminate\Http\Request;

/**
 * Read-only JSON API.
 *
 * Two decisions that matter:
 *  1. The owner filter comes from the token user's relationship, never from a
 *     ?user_id= in the query string. The API carries the same IDOR risk.
 *  2. Authorization uses authorizeForUser(), because $this->authorize() would
 *     resolve the default guard's user (web), which doesn't exist on a token
 *     request.
 */
class TaskController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'status' => 'nullable|in:all,open,done,overdue',
            'q' => 'nullable|string|max:100',
            'sort' => 'nullable|in:recent,due,priority',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $perPage = $data['per_page'] ?? 15;

        $tasks = Task::visibleTo($request->user('api'))
            ->with('project')
            ->state($data['status'] ?? 'all')
            ->search($data['q'] ?? '')
            ->sorted($data['sort'] ?? 'priority')
            ->paginate($perPage)
            ->appends($request->only(['status', 'q', 'sort', 'per_page']));

        return TaskResource::collection($tasks);
    }

    public function show(Request $request, Task $task)
    {
        $this->authorizeForUser($request->user('api'), 'view', $task);

        return new TaskResource($task);
    }
}
