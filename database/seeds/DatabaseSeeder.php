<?php

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call(UsersSeeder::class);
        // Projects before tasks: the tasks seeder places Ana's tasks into the
        // project this one creates.
        $this->call(ProjectsSeeder::class);
        $this->call(TasksSeeder::class);
        $this->call(LegacyInvoicesSeeder::class);
    }
}
