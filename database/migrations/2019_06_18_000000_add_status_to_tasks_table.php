<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Workflow stage.
 *
 * The legacy only knew the boolean `done`: "open or closed". That is enough
 * for a list, but not for measuring a process - you can't tell what is
 * stuck, what is in review, or how long a task waits in each
 * stage. The `status` column answers that, and the CFD depends on it.
 *
 * Decisions:
 *  - default 'backlog': the column arrives without rewriting rows and without
 *    breaking old INSERTs (including the CSV import, which sends no status);
 *  - `done` does NOT go away. It mirrors "status = done", kept by the model
 *    mutators. So scopes, policy, API and existing queries keep working -
 *    the change is additive, not a rewrite;
 *  - conservative backfill: done becomes 'done', the rest becomes 'backlog'.
 *    We don't invent a stage that was never recorded in the trail.
 */
class AddStatusToTasksTable extends Migration
{
    public function up()
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('status', 16)->default('backlog');
            $table->index('status');
        });

        DB::table('tasks')->where('done', true)->update(['status' => 'done']);
        DB::table('tasks')->where('done', false)->update(['status' => 'backlog']);
    }

    public function down()
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });
    }
}
