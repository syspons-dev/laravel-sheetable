<?php

namespace Syspons\Sheetable\Tests\Models;

use Illuminate\Database\Eloquent\Factories\Factory;

class ManyToManyRelationFactory extends Factory
{
    protected $model = ManyToManyRelation::class;
    public static int $number = 1;

    public function definition(): array
    {
        return [
            'id' => self::$number++,
            'label' => $this->faker->country,
        ];
    }
}
