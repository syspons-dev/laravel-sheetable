<?php

namespace Syspons\Sheetable\Tests\Feature\RelationTest;

use Illuminate\Database\Eloquent\Factories\Factory;

class OneToManyRelationFactory extends Factory
{
    protected $model = OneToManyRelation::class;

    public function definition(): array
    {
        return [
            'label' => $this->faker->text,
        ];
    }
}
