<?php

namespace Syspons\Sheetable\Tests\Feature\JoinBelongsToTest;

use Illuminate\Database\Eloquent\Factories\Factory;

class AnotherJoinableRelationFactory extends Factory
{
    protected $model = AnotherJoinableRelation::class;

    public function definition(): array
    {
        return [
            'foreign_field' => $this->faker->word(),
            'another_foreign_field' => $this->faker->word(),
        ];
    }
}
