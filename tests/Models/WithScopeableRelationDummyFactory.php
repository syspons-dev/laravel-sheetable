<?php

namespace Syspons\Sheetable\Tests\Models;

use Illuminate\Database\Eloquent\Factories\Factory;

class WithScopeableRelationDummyFactory extends Factory
{
    protected $model = WithScopeableRelationDummy::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->word,
            'description' => $this->faker->text,
        ];
    }
}
