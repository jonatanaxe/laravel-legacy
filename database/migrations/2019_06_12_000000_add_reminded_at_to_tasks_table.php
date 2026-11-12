<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marker of the last reminder sent.
 *
 * It is what makes the tasks:due-soon command idempotent: running it twice on
 * the same day (or two workers at once) doesn't send the same reminder
 * twice. Without this column, "re-engagement" turns into spam.
 */
class AddRemindedAtToTasksTable extends Migration
{
    public function up()
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->timestamp('reminded_at')->nullable()->after('version');
        });
    }

    public function down()
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('reminded_at');
        });
    }
}
