<?php

namespace Syspons\Sheetable\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;

class CountryFactory extends Factory
{
    protected $model = Country::class;
    public static int $number = 1;

    public function definition(): array
    {
        return [
            'id' => self::$number++,
            'label' => $this->faker->country,
        ];
    }
}
