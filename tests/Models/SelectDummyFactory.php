<?php

namespace Syspons\Sheetable\Tests\Models;

use Illuminate\Database\Eloquent\Factories\Factory;

class SelectDummyFactory extends Factory
{
    protected $model = SelectDummy::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->word(),
            'title2' => $this->faker->word(),
            'title3' => $this->faker->word(),
        ];
    }
}
