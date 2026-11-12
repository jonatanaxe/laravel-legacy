<?php

use Illuminate\Database\Seeder;

/**
 * Fixed demo accounts. One shared password to keep the demo simple:
 * what matters here is the isolation between them, not password strength.
 *
 * Deterministic api_token: the same email always produces the same token, so
 * the API demo command doesn't change between database resets.
 * (In production: a random token + hash, like Sanctum does.)
 */
class UsersSeeder extends Seeder
{
    public function run()
    {
        $users = [
            ['name' => 'Ana', 'email' => 'ana@example.com'],
            ['name' => 'Bruno', 'email' => 'bruno@example.com'],
            // Carla exists to demonstrate the viewer role in a project.
            ['name' => 'Carla', 'email' => 'carla@example.com'],
        ];

        foreach ($users as $data) {
            $user = App\User::firstOrCreate(
                ['email' => $data['email']],
                ['name' => $data['name'], 'password' => bcrypt('secret123')]
            );

            // Ensures the password even if the user already existed.
            $user->password = bcrypt('secret123');
            $user->api_token = hash('sha256', $data['email'] . '|lab-migration');
            $user->save();
        }
    }
}
