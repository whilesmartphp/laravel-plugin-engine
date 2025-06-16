<?php

namespace Trakli\PluginEngine\Tests\Stubs\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Trakli\PluginEngine\Tests\Stubs\Models\User;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'remember_token' => Str::random(10),
        ];
    }
}
