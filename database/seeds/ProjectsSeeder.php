<?php

use Illuminate\Database\Seeder;

/**
 * Projects with different roles on purpose.
 *
 * Ana is the owner; Bruno is a member (edits), Carla is a viewer (read only).
 * It is the permission matrix the automated tests cover - the demo shows the
 * same thing with two browsers.
 *
 * The owner also goes into the pivot as 'owner' so every role check takes
 * the same path.
 */
class ProjectsSeeder extends Seeder
{
    /** Single source of the demo project's name (TasksSeeder reads it too). */
    const NAME = 'Laravel 8.4 Migration';

    public function run()
    {
        $ana = App\User::where('email', 'ana@example.com')->first();
        $bruno = App\User::where('email', 'bruno@example.com')->first();
        $carla = App\User::where('email', 'carla@example.com')->first();

        if (!$ana || !$bruno || !$carla) {
            return;
        }

        // The project name = this case's own work: taking Laravel 5.8/PHP 7.2
        // to 8.4, one step per deploy. The name has to match the tasks inside,
        // or the demo contradicts itself.
        $project = App\Project::firstOrNew(['name' => self::NAME]);

        if (!$project->exists) {
            // user_id is not fillable: the owner is assigned explicitly,
            // never through mass assignment.
            $project->user_id = $ana->id;
            $project->description = 'From Laravel 5.8/PHP 7.2 to 8.4, one step per deploy.';
            $project->save();
        }

        $project->users()->syncWithoutDetaching([
            $ana->id => ['role' => 'owner'],
            $bruno->id => ['role' => 'member'],
            $carla->id => ['role' => 'viewer'],
        ]);
    }
}
