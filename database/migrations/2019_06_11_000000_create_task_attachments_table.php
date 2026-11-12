<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task attachments.
 *
 * The file is NOT in the database: it is on disk (storage/app/attachments),
 * outside the document root. The database keeps the path and metadata. That
 * avoids binaries in dumps, giant backups and, above all, files served directly
 * by nginx - downloads go through a route with a policy.
 *
 * size in bytes (unsignedBigInteger): leaves headroom beyond a 32-bit int
 * for the future.
 */
class CreateTaskAttachmentsTable extends Migration
{
    public function up()
    {
        Schema::create('task_attachments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('task_id');
            $table->unsignedBigInteger('user_id');
            $table->string('original_name', 200);
            $table->string('path', 255);
            $table->string('mime', 120);
            $table->unsignedBigInteger('size');
            $table->timestamps();

            $table->index('task_id');
            $table->foreign('task_id')->references('id')->on('tasks')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('task_attachments');
    }
}
