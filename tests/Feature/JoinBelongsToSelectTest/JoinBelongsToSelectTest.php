<?php

namespace Syspons\Sheetable\Tests\Feature\JoinBelongsToSelectTest;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

/**
 *
 */
class JoinBelongsToSelectTest extends JoinBelongsToSelectTestCase
{
    use RefreshDatabase;

    public function test_routes_exist(): void
    {
        $expectedRoutes = [
            'joinable_dummies.import',
            'joinable_dummies.export',
            'joinable_dummies.template',
        ];
        $registeredRoutes = array_keys(Route::getRoutes()->getRoutesByName());
        foreach ($expectedRoutes as $route) {
            $this->assertContains($route, $registeredRoutes);
        }
    }

    public function test_store_joinable_file()
    {
        $expectedName = 'joinable_dummies.xlsx';
        JoinableDummy::factory()->count(3)->make()->each(function ($item, $key) {
            $item->title = 'title '.++$key;
            $item->description = 'description '.$key;
            $relation = [
                'foreign_field' => 'foreign_field '.$key,
                'another_foreign_field' => 'another_foreign_field '.$key,
                'yet_another_foreign_field' => 'yet_another_foreign_field '.$key,
            ];
            $select = JoinableSelectRelation::factory()->create($relation);
            $item->joinable_select_relation()->associate($select);
            $except = JoinableExceptRelation::factory()->create($relation);
            $item->joinable_except_relation()->associate($except);
            $both = JoinableBothRelation::factory()->create($relation);
            $item->joinable_both_relation()->associate($both);
            $item->save();
        });
        $response = $this->get(route('joinable_dummies.export'))
            ->assertStatus(200)
            ->assertDownload($expectedName);
        $this->assertExpectedSpreadsheetResponse($response, __DIR__.'/'.$expectedName);
    }
}
