<?php

namespace Syspons\Sheetable\Tests\Feature\RelationTest;

use Illuminate\Database\Eloquent\Factories\Factory;

class ManyToManyRelationFactory extends Factory
{
    protected $model = ManyToManyRelation::class;

    public function definition(): array
    {
        return [
            'label' => $this->faker->text,
        ];
    }
}
