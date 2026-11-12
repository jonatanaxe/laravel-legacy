<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot with a role.
 *
 * unique(project_id, user_id): the same person can't join the same project
 * twice. Without it, a repeated POST would create two rows with different
 * roles and authorization would depend on query order.
 *
 * Role is a string, not an enum: MySQL is slow to alter enums, and the valid
 * list already lives in the model (Project::ROLES), validated on the server.
 */
class CreateProjectUserTable extends Migration
{
    public function up()
    {
        Schema::create('project_user', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('user_id');
            $table->string('role', 16)->default('member');
            $table->timestamps();

            $table->unique(['project_id', 'user_id']);
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('project_user');
    }
}
