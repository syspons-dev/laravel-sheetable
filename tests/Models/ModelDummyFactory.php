<?php

namespace Syspons\Sheetable\Tests\Models;

use Illuminate\Database\Eloquent\Factories\Factory;

class ModelDummyFactory extends Factory
{
    protected $model = ModelDummy::class;
    static int $number = 1;

    public function definition(): array
    {
        return [
            'id' => self::$number++,
            'title' => $this->faker->word,
            'description' => $this->faker->text,
        ];
    }
}
