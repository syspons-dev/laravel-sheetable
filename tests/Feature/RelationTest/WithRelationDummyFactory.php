<?php

namespace Syspons\Sheetable\Tests\Feature\RelationTest;

use Illuminate\Database\Eloquent\Factories\Factory;

class WithRelationDummyFactory extends Factory
{
    protected $model = WithRelationDummy::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->word,
            'description' => $this->faker->text,
        ];
    }
}
