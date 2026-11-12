<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Referential integrity: a task without an owner doesn't exist.
 *
 * Honest detail: SQLite (used in tests) ignores ALTER TABLE ADD
 * CONSTRAINT - Laravel handles foreign keys "on table creation". So this
 * constraint applies on MySQL (where the app runs) and is a no-op in tests.
 * Documented instead of hidden.
 *
 * onDelete('cascade'): deleting a user deletes their tasks. The trail in
 * task_events has NO FK precisely to survive that.
 */
class AddForeignKeyToTasksUser extends Migration
{
    public function up()
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreign('user_id')
                ->references('id')->on('users')
                ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });
    }
}
