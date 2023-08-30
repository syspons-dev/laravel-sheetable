<?php

namespace Syspons\Sheetable\Tests\Feature\JoinBelongsToSelectTest;

use Illuminate\Database\Eloquent\Factories\Factory;

class JoinableBothRelationFactory extends Factory
{
    protected $model = JoinableBothRelation::class;

    public function definition(): array
    {
        return [
            'foreign_field' => $this->faker->word(),
            'another_foreign_field' => $this->faker->word(),
            'yet_another_foreign_field' => $this->faker->word(),
        ];
    }
}
