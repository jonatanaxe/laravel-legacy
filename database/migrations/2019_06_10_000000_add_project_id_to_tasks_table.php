<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tasks inside a project.
 *
 * NULLABLE on purpose: a personal task (project_id NULL) keeps existing
 * with the old behavior - only the owner sees it. That is what allows
 * migrating a legacy database without rewriting every row.
 *
 * onDelete('set null'): deleting a project does NOT delete anyone's work
 * through a silent cascade. The controller also nulls it in a transaction,
 * because SQLite (tests) ignores ALTER TABLE ADD CONSTRAINT.
 */
class AddProjectIdToTasksTable extends Migration
{
    public function up()
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->unsignedBigInteger('project_id')->nullable()->after('user_id');

            $table->index('project_id');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->dropIndex(['project_id']);
            $table->dropColumn('project_id');
        });
    }
}
