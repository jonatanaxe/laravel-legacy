<?php

namespace App;

use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'password',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token', 'api_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Projects the user belongs to (with the role on the pivot).
     */
    public function projects()
    {
        return $this->belongsToMany(Project::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function taskImports()
    {
        return $this->hasMany(TaskImport::class);
    }

    /**
     * Projects where the user can create/edit tasks (owner or member).
     * Viewers are left out: this builds the form's select, but what really
     * blocks access is the policy.
     */
    public function writableProjects()
    {
        return $this->projects()->wherePivotIn('role', Project::WRITE_ROLES);
    }
}
