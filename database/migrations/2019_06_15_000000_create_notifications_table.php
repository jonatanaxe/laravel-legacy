<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * In-app notifications (database channel).
 *
 * The reminder doesn't vanish in an email nobody reads: it is recorded, shows
 * up in the app and can be marked as read. A mail channel can come later
 * without changing the job - only the notification's via().
 *
 * uuid as the PK: notifications can be created by several workers without
 * coordination and with no risk of sequential id collisions.
 */
class CreateNotificationsTable extends Migration
{
    public function up()
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('notifications');
    }
}
