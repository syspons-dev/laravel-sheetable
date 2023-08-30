<?php

namespace Syspons\Sheetable\Tests\Feature\JoinTranslatableTest;

use Illuminate\Database\Eloquent\Factories\Factory;

class ManyToManyTranslatableDummyFactory extends Factory
{
    protected $model = ManyToManyTranslatableDummy::class;

    public function definition(): array
    {
        return [
            'title' => [
                'en' => $this->faker->text(),
                'de' => $this->faker->text(),
            ],
        ];
    }
}
