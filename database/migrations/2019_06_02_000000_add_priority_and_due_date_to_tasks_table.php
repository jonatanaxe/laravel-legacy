<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Priority and due date.
 *
 * Two new columns on a table that already has data: the classic production
 * migration case. That is why both fields are nullable/defaulted - a migration
 * that requires a value on existing rows breaks the deploy.
 *
 * The (user_id, done, due_date) index serves the default list: "my tasks,
 * filtered by status, sorted by due date". Without it the database scans
 * the whole table on every page.
 */
class AddPriorityAndDueDateToTasksTable extends Migration
{
    public function up()
    {
        Schema::table('tasks', function (Blueprint $table) {
            // low | normal | high - a short string with a default avoids NULL in the
            // sorting code and allows a new value without a migration.
            $table->string('priority', 10)->default('normal')->after('description');
            $table->date('due_date')->nullable()->after('priority');

            $table->index(['user_id', 'done', 'due_date'], 'tasks_user_status_due_index');
        });
    }

    public function down()
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex('tasks_user_status_due_index');
            $table->dropColumn(['priority', 'due_date']);
        });
    }
}
