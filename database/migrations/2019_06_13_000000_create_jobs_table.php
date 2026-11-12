<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Database queue (database driver).
 *
 * Here .env uses QUEUE_CONNECTION=sync, so the job runs in the same
 * process and needs no worker. In production you switch the variable to
 * 'database' (or redis) and start `php artisan queue:work`: the job code is
 * the same. Having the table proves the queue is configurable, not a promise.
 */
class CreateJobsTable extends Migration
{
    public function up()
    {
        Schema::create('jobs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('jobs');
    }
}
