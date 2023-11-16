<?php

namespace Syspons\Sheetable\Tests\Feature\EmptyRowsTest;

use Illuminate\Database\Eloquent\Factories\Factory;

class EmptyRowFactory extends Factory
{
    protected $model = EmptyRow::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->word(),
            'date_time' => '2020-01-01',
        ];
    }
}
