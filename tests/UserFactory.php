<?php

namespace Syspons\Sheetable\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use JetBrains\PhpStorm\ArrayShape;

class UserFactory extends Factory
{
    protected $model = User::class;

    #[ArrayShape(['firstname' => "string", 'lastname' => "string"])]
    public function definition(): array
    {
        return [
            'id' => 1,
            'firstname' => $this->faker->firstName(),
            'lastname' => $this->faker->lastName(),
        ];
    }
}
