<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTasksTable extends Migration
{
    public function up()
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->bigIncrements('id');
            // Task owner. Kept out of the model's $fillable on purpose:
            // the owner is always set by the server, never by the form.
            $table->unsignedBigInteger('user_id');
            $table->string('title', 120);
            $table->text('description')->nullable();
            $table->boolean('done')->default(false);
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('tasks');
    }
}
