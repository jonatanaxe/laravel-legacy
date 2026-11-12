<?php

namespace App\Http\Controllers;

use App\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * My account: profile, projects and API access.
 *
 * The token existed since the API was built, but no screen showed it: the
 * API was unreachable without the seed data at hand. Here it appears masked,
 * with a usage example, and can be rotated.
 *
 * Known risk (docs/cto-brief.md): the token is stored in plain text
 * (guard 'token' with hash => false). Production would use Sanctum, hashed.
 */
class AccountController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();

        // Role without a query per project: the pivot comes with the user's projects.
        $memberships = $user->projects()->get()->keyBy('id');

        $projects = Project::visibleTo($user)->orderBy('name')->get()
            ->map(function (Project $project) use ($user, $memberships) {
                $project->my_role = $project->user_id === $user->id
                    ? 'owner'
                    : optional(optional($memberships->get($project->id))->pivot)->role;

                return $project;
            });

        return view('account.show', [
            'user' => $user,
            'projects' => $projects,
            'apiBase' => url('/api'),
        ]);
    }

    public function regenerateToken(Request $request)
    {
        $user = $request->user();

        // forceFill: api_token is not fillable (and must not be).
        $user->forceFill(['api_token' => Str::random(60)])->save();

        return redirect()->route('account.show')
            ->with('status', 'New token generated. The previous one stopped working now.');
    }
}
