<?php

namespace Syspons\Sheetable\Tests\Feature\EmptyRowsTest;

use Illuminate\Database\Eloquent\Factories\Factory;

class SimpleDummyFactory extends Factory
{
    protected $model = SimpleDummy::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->word(),
            'date_time' => '2020-01-01',
        ];
    }
}
