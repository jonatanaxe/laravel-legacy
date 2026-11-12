<?php

namespace App\Policies;

use App\Project;
use App\User;

/**
 * Role-based authorization inside a project.
 *
 * The question is never "does this user exist?" but "what is their role in
 * THIS project?". A member of one project is not a member of another.
 */
class ProjectPolicy
{
    public function view(User $user, Project $project)
    {
        return $project->roleFor($user) !== null;
    }

    /**
     * Creating a task in the project: writers (owner/member), never viewers.
     */
    public function createTask(User $user, Project $project)
    {
        return $project->hasRole($user, Project::WRITE_ROLES);
    }

    /**
     * Renaming/describing the project and managing members: owner only.
     * Members work inside it; they don't rearrange the house.
     */
    public function update(User $user, Project $project)
    {
        return $project->roleFor($user) === 'owner';
    }

    public function manageMembers(User $user, Project $project)
    {
        return $project->roleFor($user) === 'owner';
    }

    public function delete(User $user, Project $project)
    {
        return $project->roleFor($user) === 'owner';
    }
}
