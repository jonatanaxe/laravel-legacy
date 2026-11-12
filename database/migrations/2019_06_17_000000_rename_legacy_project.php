<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Renames the project that came from the old seed.
 *
 * The name "Migração Magento" was a leftover from another client context and
 * contradicted the project's own tasks (all of them Laravel upgrade work).
 * Changing the seeder doesn't fix a row that already exists in the database,
 * so the fix ships as a data migration - idempotent, with a rollback, and it
 * also runs for anyone who had already seeded the database.
 *
 * Applied migrations are history: the values below are kept as they were.
 * The later English rename is its own migration (2026_11_12_...).
 */
class RenameLegacyProject extends Migration
{
    const FROM = 'Migração Magento';

    const TO = 'Migração Laravel 8.4';

    public function up()
    {
        DB::table('projects')
            ->where('name', self::FROM)
            ->update([
                'name' => self::TO,
                'updated_at' => now(),
            ]);
    }

    public function down()
    {
        DB::table('projects')
            ->where('name', self::TO)
            ->update([
                'name' => self::FROM,
                'updated_at' => now(),
            ]);
    }
}
