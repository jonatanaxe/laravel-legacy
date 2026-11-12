<?php

namespace Tests\Feature;

use App\Project;
use App\Task;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Per-project permission matrix.
 *
 * owner   - project owner: manages members, deletes the project, edits everything
 * member  - works on the project's tasks; doesn't delete the project
 * viewer  - read only; doesn't create, edit or delete
 * outside - sees nothing of the project, neither in the list nor by id in the URL
 *
 * The most important case here is leakage between teams: a member of
 * project A can't see or attach a task to project B by changing the
 * project_id in the form.
 */
class ProjectAccessTest extends TestCase
{
    use RefreshDatabase;

    private function makeProject(User $owner, array $members = [])
    {
        $project = new Project(['name' => $owner->name . '\'s project']);
        $project->user_id = $owner->id;
        $project->save();

        $project->users()->attach($owner->id, ['role' => 'owner']);

        foreach ($members as $member) {
            $project->users()->attach($member[0]->id, ['role' => $member[1]]);
        }

        return $project;
    }

    private function taskIn(Project $project, User $creator, array $overrides = [])
    {
        $task = factory(Task::class)->create(array_merge([
            'user_id' => $creator->id,
        ], $overrides));

        // project_id is not fillable: explicit assignment, like in the controller.
        $task->project_id = $project->id;
        $task->save();

        return $task;
    }

    public function test_owner_creates_project_and_joins_the_pivot_as_owner()
    {
        $ana = factory(User::class)->create();

        $this->actingAs($ana)
            ->post('/projects', [
                'name' => 'Migration',
                'description' => 'Platform team',
            ])
            ->assertRedirect();

        $project = Project::where('name', 'Migration')->firstOrFail();

        $this->assertSame($ana->id, $project->user_id);
        $this->assertDatabaseHas('project_user', [
            'project_id' => $project->id,
            'user_id' => $ana->id,
            'role' => 'owner',
        ]);
    }

    public function test_member_sees_project_task_but_not_the_owners_personal_one()
    {
        $ana = factory(User::class)->create();
        $bruno = factory(User::class)->create();
        $project = $this->makeProject($ana, [[$bruno, 'member']]);

        $projectTask = $this->taskIn($project, $ana, ['title' => 'Project task']);
        $personal = factory(Task::class)->create([
            'user_id' => $ana->id,
            'title' => 'Ana personal task',
        ]);

        $this->actingAs($bruno)
            ->get('/tasks')
            ->assertStatus(200)
            ->assertSee('Project task')
            ->assertDontSee('Ana personal task');

        $this->actingAs($bruno)->get('/tasks/' . $projectTask->id)->assertStatus(200);
        $this->actingAs($bruno)->get('/tasks/' . $personal->id)->assertStatus(403);
    }

    public function test_member_creates_and_edits_a_project_task()
    {
        $ana = factory(User::class)->create();
        $bruno = factory(User::class)->create();
        $project = $this->makeProject($ana, [[$bruno, 'member']]);

        $this->actingAs($bruno)
            ->post('/tasks', [
                'title' => 'Task created by the member',
                'project_id' => $project->id,
            ])
            ->assertRedirect('/tasks');

        $task = Task::where('title', 'Task created by the member')->firstOrFail();

        // Task owner = whoever created it; project = the shared one.
        $this->assertSame($bruno->id, $task->user_id);
        $this->assertSame($project->id, $task->project_id);

        // And Ana, the project owner, can edit it too.
        $this->actingAs($ana)
            ->put('/tasks/' . $task->id, [
                'title' => 'Edited by the owner',
                'version' => $task->version,
            ])
            ->assertRedirect('/tasks');
    }

    public function test_viewer_reads_but_neither_edits_nor_creates()
    {
        $ana = factory(User::class)->create();
        $carla = factory(User::class)->create();
        $project = $this->makeProject($ana, [[$carla, 'viewer']]);
        $task = $this->taskIn($project, $ana);

        $this->actingAs($carla)->get('/projects/' . $project->id)->assertStatus(200);
        $this->actingAs($carla)->get('/tasks/' . $task->id)->assertStatus(200);

        $this->actingAs($carla)->get('/tasks/' . $task->id . '/edit')->assertStatus(403);
        $this->actingAs($carla)
            ->put('/tasks/' . $task->id, ['title' => 'Tentativa'])
            ->assertStatus(403);
        $this->actingAs($carla)
            ->delete('/tasks/' . $task->id)
            ->assertStatus(403);

        // And project_id in the POST doesn't help: the policy blocks it before saving.
        $this->actingAs($carla)
            ->post('/tasks', [
                'title' => 'Viewer task',
                'project_id' => $project->id,
            ])
            ->assertStatus(403);

        $this->assertDatabaseMissing('tasks', ['title' => 'Viewer task']);
    }

    public function test_outsider_sees_nothing_of_the_project()
    {
        $ana = factory(User::class)->create();
        $bruno = factory(User::class)->create();
        $project = $this->makeProject($ana);
        $task = $this->taskIn($project, $ana, ['title' => 'Project secret']);

        $this->actingAs($bruno)
            ->get('/projects')
            ->assertDontSee($project->name);

        $this->actingAs($bruno)->get('/projects/' . $project->id)->assertStatus(403);
        $this->actingAs($bruno)->get('/tasks/' . $task->id)->assertStatus(403);

        // Leak through the form: sending the project_id grants no access.
        $this->actingAs($bruno)
            ->post('/tasks', [
                'title' => 'Enxerto',
                'project_id' => $project->id,
            ])
            ->assertStatus(403);

        $this->assertDatabaseMissing('tasks', ['title' => 'Enxerto']);
    }

    public function test_forged_project_id_on_update_is_ignored()
    {
        $ana = factory(User::class)->create();
        $bruno = factory(User::class)->create();
        $project = $this->makeProject($ana);

        $personal = factory(Task::class)->create([
            'user_id' => $ana->id,
            'project_id' => null,
        ]);

        // Ana owns the task, so the update passes - but project_id is not
        // fillable and the controller doesn't assign it. The task stays personal.
        $this->actingAs($ana)
            ->put('/tasks/' . $personal->id, [
                'title' => 'Still personal',
                'version' => $personal->version,
                'project_id' => $project->id,
            ])
            ->assertRedirect('/tasks');

        $this->assertNull($personal->fresh()->project_id);
    }

    public function test_member_neither_deletes_the_project_nor_manages_members()
    {
        $ana = factory(User::class)->create();
        $bruno = factory(User::class)->create();
        $carla = factory(User::class)->create();
        $project = $this->makeProject($ana, [[$bruno, 'member']]);

        $this->actingAs($bruno)
            ->delete('/projects/' . $project->id)
            ->assertStatus(403);

        $this->actingAs($bruno)
            ->post('/projects/' . $project->id . '/members', [
                'email' => $carla->email,
                'role' => 'member',
            ])
            ->assertStatus(403);

        $this->assertDatabaseHas('projects', ['id' => $project->id]);
        $this->assertDatabaseMissing('project_user', [
            'project_id' => $project->id,
            'user_id' => $carla->id,
        ]);
    }

    public function test_owner_adds_and_removes_a_member()
    {
        $ana = factory(User::class)->create();
        $carla = factory(User::class)->create();
        $project = $this->makeProject($ana);

        $this->actingAs($ana)
            ->post('/projects/' . $project->id . '/members', [
                'email' => $carla->email,
                'role' => 'viewer',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('project_user', [
            'project_id' => $project->id,
            'user_id' => $carla->id,
            'role' => 'viewer',
        ]);

        $this->actingAs($ana)
            ->delete('/projects/' . $project->id . '/members/' . $carla->id)
            ->assertRedirect();

        $this->assertDatabaseMissing('project_user', [
            'project_id' => $project->id,
            'user_id' => $carla->id,
        ]);
    }

    public function test_owner_cannot_be_removed_from_the_project()
    {
        $ana = factory(User::class)->create();
        $project = $this->makeProject($ana);

        $this->actingAs($ana)
            ->delete('/projects/' . $project->id . '/members/' . $ana->id)
            ->assertStatus(422);

        $this->assertDatabaseHas('project_user', [
            'project_id' => $project->id,
            'user_id' => $ana->id,
        ]);
    }

    public function test_deleting_a_project_keeps_its_tasks_as_personal()
    {
        $ana = factory(User::class)->create();
        $project = $this->makeProject($ana);
        $task = $this->taskIn($project, $ana);

        $this->actingAs($ana)
            ->delete('/projects/' . $project->id)
            ->assertRedirect('/projects');

        // The task wasn't deleted: it became the creator's personal task.
        $this->assertDatabaseHas('tasks', ['id' => $task->id]);
        $this->assertNull($task->fresh()->project_id);
        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }

    public function test_member_demoted_to_viewer_loses_edit_on_own_task()
    {
        $ana = factory(User::class)->create();
        $bruno = factory(User::class)->create();
        $project = $this->makeProject($ana, [[$bruno, 'member']]);
        $task = $this->taskIn($project, $bruno);

        // Bruno was a member when he created it; Ana demotes his role.
        $project->users()->updateExistingPivot($bruno->id, ['role' => 'viewer']);

        $this->actingAs($bruno)
            ->put('/tasks/' . $task->id, [
                'title' => 'Can I still?',
                'version' => $task->version,
            ])
            ->assertStatus(403);

        $this->assertNotSame('Can I still?', $task->fresh()->title);
    }
}
