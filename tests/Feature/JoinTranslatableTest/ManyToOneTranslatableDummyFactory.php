<?php

namespace Syspons\Sheetable\Tests\Feature\JoinTranslatableTest;

use Illuminate\Database\Eloquent\Factories\Factory;

class ManyToOneTranslatableDummyFactory extends Factory
{
    protected $model = ManyToOneTranslatableDummy::class;

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
