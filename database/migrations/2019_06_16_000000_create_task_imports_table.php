<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Import report.
 *
 * Each upload becomes a row here, with counters and the list of errors per
 * line of the file. Without it, an import is a black box: the user sends
 * 500 lines, gets "done" and doesn't know which 37 were rejected.
 *
 * errors as TEXT (JSON), not a child table: it is an immutable report, always
 * read together with the import, and not worth a join per line.
 */
class CreateTaskImportsTable extends Migration
{
    public function up()
    {
        Schema::create('task_imports', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->string('original_name', 200);
            $table->string('status', 16)->default('pending');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('imported_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->text('errors')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('task_imports');
    }
}
