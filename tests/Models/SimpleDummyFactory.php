<?php

namespace Syspons\Sheetable\Tests\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use JetBrains\PhpStorm\ArrayShape;

class SimpleDummyFactory extends Factory
{
    protected $model = SimpleDummy::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->word(),
        ];
    }
}
