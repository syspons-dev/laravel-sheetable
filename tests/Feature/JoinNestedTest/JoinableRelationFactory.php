<?php

namespace Syspons\Sheetable\Tests\Feature\JoinNestedTest;

use Illuminate\Database\Eloquent\Factories\Factory;

class JoinableRelationFactory extends Factory
{
    protected $model = JoinableRelation::class;

    public function definition(): array
    {
        return [
            'foreign_field' => $this->faker->word(),
            'another_foreign_field' => $this->faker->word(),
        ];
    }
}
