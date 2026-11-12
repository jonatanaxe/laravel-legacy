<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The product went English: renames the demo project in databases that were
 * already seeded.
 *
 * Same pattern as 2019_06_17_000000_rename_legacy_project: changing the
 * seeder doesn't touch rows that already exist, and an applied migration is
 * never edited - a new one carries the change. Idempotent (matches only the
 * old name) and reversible.
 */
class RenameProjectToEnglish extends Migration
{
    const FROM = 'Migração Laravel 8.4';

    const TO = 'Laravel 8.4 Migration';

    const DESCRIPTION_FROM = 'Salto de Laravel 5.8/PHP 7.2 para 8.4, um degrau por deploy.';

    const DESCRIPTION_TO = 'From Laravel 5.8/PHP 7.2 to 8.4, one step per deploy.';

    public function up()
    {
        DB::table('projects')->where('name', self::FROM)->update([
            'name' => self::TO,
            'updated_at' => now(),
        ]);

        DB::table('projects')->where('description', self::DESCRIPTION_FROM)->update([
            'description' => self::DESCRIPTION_TO,
        ]);
    }

    public function down()
    {
        DB::table('projects')->where('name', self::TO)->update([
            'name' => self::FROM,
            'updated_at' => now(),
        ]);

        DB::table('projects')->where('description', self::DESCRIPTION_TO)->update([
            'description' => self::DESCRIPTION_FROM,
        ]);
    }
}
