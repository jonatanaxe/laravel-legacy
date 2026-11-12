<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user API token.
 *
 * unique: the token is the identity on the request; it can't repeat.
 * nullable: users created before the column existed stay valid.
 *
 * We store the token in plain text on purpose (config/auth.php: hash=false)
 * because the audience here is a demo with a visible token. In production the
 * right thing is hash + an identifying prefix, like Sanctum does.
 */
class AddApiTokenToUsersTable extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('api_token', 80)->nullable()->unique()->after('password');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['api_token']);
            $table->dropColumn('api_token');
        });
    }
}
