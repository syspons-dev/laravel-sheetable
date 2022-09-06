<?php

namespace Syspons\Sheetable\Tests\Models;

use Illuminate\Database\Eloquent\Factories\Factory;

class ScopeableManyToManyRelationFactory extends Factory
{
    protected $model = ScopeableManyToManyRelation::class;

    public function definition(): array
    {
        return [
            'label' => $this->faker->text,
        ];
    }
}
