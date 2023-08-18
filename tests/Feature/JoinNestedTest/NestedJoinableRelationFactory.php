<?php

namespace Syspons\Sheetable\Tests\Feature\JoinNestedTest;

use Illuminate\Database\Eloquent\Factories\Factory;

class NestedJoinableRelationFactory extends Factory
{
    protected $model = NestedJoinableRelation::class;

    public function definition(): array
    {
        return [
            'foreign_field' => $this->faker->word(),
            'another_foreign_field' => $this->faker->word(),
        ];
    }
}
