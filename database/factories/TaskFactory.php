<?php

use Faker\Generator as Faker;

$factory->define(App\Task::class, function (Faker $faker) {
    return [
        'user_id' => function () {
            return factory(App\User::class)->create()->id;
        },
        'title' => $faker->sentence(4),
        'description' => $faker->optional()->paragraph(),
        // Explicit defaults: a test that isn't about priority or due date
        // needs a predictable value, not faker's luck.
        'priority' => 'normal',
        'due_date' => null,
        'done' => $faker->boolean(30),
    ];
});
