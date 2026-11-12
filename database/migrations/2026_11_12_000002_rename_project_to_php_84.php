<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Laravel 8.4" does not exist: Laravel 8 stopped at 8.83. The target of the
 * case is the runtime, PHP 8.4 (on Laravel 13), so the demo project is named
 * after it.
 *
 * Same pattern as the earlier renames: the seeder only covers new databases,
 * so existing rows get a data migration. Idempotent (matches only the old
 * name) and reversible.
 */
class RenameProjectToPhp84 extends Migration
{
    const FROM = 'Laravel 8.4 Migration';

    const TO = 'PHP 8.4 Migration';

    public function up()
    {
        DB::table('projects')->where('name', self::FROM)->update([
            'name' => self::TO,
            'updated_at' => now(),
        ]);
    }

    public function down()
    {
        DB::table('projects')->where('name', self::TO)->update([
            'name' => self::FROM,
            'updated_at' => now(),
        ]);
    }
}
