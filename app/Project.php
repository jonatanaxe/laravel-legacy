<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    /**
     * Accepted roles. Single list: validation, policy and views read it here.
     *
     * owner  - deletes the project, manages members, everything in it
     * member - creates/edits/deletes the project's tasks
     * viewer - read only (the "client" who follows along without touching)
     */
    const ROLES = ['owner', 'member', 'viewer'];

    const WRITE_ROLES = ['owner', 'member'];

    protected $fillable = ['name', 'description'];

    protected $casts = [
        'user_id' => 'integer',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    /**
     * The user's role in the project, or null if they are not a member.
     *
     * The owner answers 'owner' even if the pivot row disappears - the
     * projects.user_id column is the source of truth, not a side effect.
     */
    public function roleFor(User $user)
    {
        if ($this->user_id === $user->id) {
            return 'owner';
        }

        $member = $this->users()->where('users.id', $user->id)->first();

        return $member ? $member->pivot->role : null;
    }

    public function hasRole(User $user, array $roles)
    {
        return in_array($this->roleFor($user), $roles, true);
    }

    /**
     * Projects the user can see: the ones they created or belong to. The
     * query filters in the database - it doesn't load everything and filter
     * in PHP.
     */
    public function scopeVisibleTo($query, User $user)
    {
        return $query->where(function ($q) use ($user) {
            $q->where('projects.user_id', $user->id)
                ->orWhereHas('users', function ($sub) use ($user) {
                    $sub->where('users.id', $user->id);
                });
        });
    }
}
