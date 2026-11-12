<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optimistic lock.
 *
 * Two tabs open on the same task: without this the last "Save" wins and the
 * first one silently disappears. With version, the UPDATE only happens if the
 * row is still at the version the form read; if not, the server answers
 * 409 and asks for a reload.
 *
 * unsignedInteger: the version only grows, it is never negative.
 */
class AddVersionToTasksTable extends Migration
{
    public function up()
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->unsignedInteger('version')->default(0)->after('done');
        });
    }

    public function down()
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('version');
        });
    }
}
