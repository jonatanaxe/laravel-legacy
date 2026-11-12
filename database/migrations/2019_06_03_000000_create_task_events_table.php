<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit trail.
 *
 * An application log (log/storage) doesn't answer "who changed this task". A
 * table does, and it survives log rotation. We store:
 *   - who (user_id)
 *   - what (action: created/updated/completed/reopened/deleted)
 *   - when (created_at)
 *   - the diff as JSON (meta), so there is no column per field
 *
 * No foreign key with cascade: if the task is deleted, the event remains
 * the evidence that it existed.
 */
class CreateTaskEventsTable extends Migration
{
    public function up()
    {
        Schema::create('task_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('task_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action', 20);
            $table->text('meta')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['task_id', 'created_at']);
            $table->index('user_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('task_events');
    }
}
