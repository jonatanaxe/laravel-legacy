<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Projects: work stops being 100% individual.
 *
 * user_id is the project OWNER. Members live in the project_user pivot
 * with a role (owner/member/viewer). The owner is also stored in the pivot
 * as 'owner' so every role query takes the same path - the column here
 * stays the source of truth for "who can delete".
 */
class CreateProjectsTable extends Migration
{
    public function up()
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->string('name', 120);
            $table->string('description', 2000)->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('projects');
    }
}
