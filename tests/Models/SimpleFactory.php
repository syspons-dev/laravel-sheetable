<?php

namespace Syspons\Sheetable\Tests\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use JetBrains\PhpStorm\ArrayShape;

class SimpleFactory extends Factory
{
    protected $model = Simple::class;

    #[ArrayShape(['firstname' => "string", 'lastname' => "string"])]
    public function definition(): array
    {
        return [
            'firstname' => $this->faker->firstName(),
            'lastname' => $this->faker->lastName(),
        ];
    }
}