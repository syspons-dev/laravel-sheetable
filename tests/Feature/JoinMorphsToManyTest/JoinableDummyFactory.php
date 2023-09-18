<?php

namespace Syspons\Sheetable\Tests\Feature\JoinMorphsToManyTest;

use Illuminate\Database\Eloquent\Factories\Factory;

class JoinableDummyFactory extends Factory
{
    protected $model = JoinableDummy::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->word(),
            'description' => $this->faker->word(),
        ];
    }
}
