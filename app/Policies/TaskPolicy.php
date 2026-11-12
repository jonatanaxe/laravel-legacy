<?php

namespace App\Policies;

use App\Project;
use App\Task;
use App\User;

/**
 * Composite authorization: task owner OR role in the project.
 *
 * One rule, no exceptions:
 *  - personal task (project_id NULL): owner only, with strict ===;
 *  - project task: the ROLE in the project decides, not who created the task.
 *
 * The second part is deliberate. If "I created it" still counted after being
 * demoted to viewer, revoking access would be theater. Tested in
 * ProjectAccessTest::test_member_demoted_to_viewer_loses_edit_on_own_task.
 */
class TaskPolicy
{
    const VIEW_ROLES = ['owner', 'member', 'viewer'];
    const WRITE_ROLES = ['owner', 'member'];

    public function view(User $user, Task $task)
    {
        return $this->allows($user, $task, self::VIEW_ROLES);
    }

    public function update(User $user, Task $task)
    {
        return $this->allows($user, $task, self::WRITE_ROLES);
    }

    public function delete(User $user, Task $task)
    {
        return $this->allows($user, $task, self::WRITE_ROLES);
    }

    public function restore(User $user, Task $task)
    {
        return $this->allows($user, $task, self::WRITE_ROLES);
    }

    public function forceDelete(User $user, Task $task)
    {
        return $this->allows($user, $task, self::WRITE_ROLES);
    }

    private function allows(User $user, Task $task, array $roles)
    {
        if ($task->project_id === null) {
            // === not ==: '1' == 1 would pass, and another user's id could
            // pass a loose comparison. The 'integer' cast guarantees the type.
            return $user->id === $task->user_id;
        }

        $project = $task->project;

        // Project deleted while the task still points to it (legacy database
        // without a FK): deny. Failing closed beats opening by accident.
        if ($project === null) {
            return false;
        }

        return $project->hasRole($user, $roles);
    }
}
