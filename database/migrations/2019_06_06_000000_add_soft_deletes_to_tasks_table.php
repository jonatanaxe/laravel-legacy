<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Soft delete.
 *
 * A real delete destroys the audit trail of a record that may need to be
 * proven later. deleted_at keeps the row out of normal queries
 * (the SoftDeletes trait applies the scope) and allows restoring it.
 *
 * Index on deleted_at: every list now has "WHERE deleted_at IS NULL".
 */
class AddSoftDeletesToTasksTable extends Migration
{
    public function up()
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->softDeletes();
            $table->index('deleted_at');
        });
    }

    public function down()
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['deleted_at']);
            $table->dropSoftDeletes();
        });
    }
}
