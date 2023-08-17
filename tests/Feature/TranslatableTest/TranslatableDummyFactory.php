<?php

namespace Syspons\Sheetable\Tests\Feature\TranslatableTest;

use Illuminate\Database\Eloquent\Factories\Factory;

class TranslatableDummyFactory extends Factory
{
    protected $model = TranslatableDummy::class;

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
