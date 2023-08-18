<?php

namespace Syspons\Sheetable\Tests\Feature\JoinNestedTest;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

/**
 *
 */
class JoinNestedTest extends JoinNestedTestCase
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
            $join = JoinableRelation::factory()->make([
                'foreign_field' => 'foreign_field '.$key,
                'another_foreign_field' => 'another_foreign_field '.$key,
            ]);
            $nested_join = NestedJoinableRelation::factory()->create([
                'foreign_field' => 'foreign_field '.$key,
                'another_foreign_field' => 'another_foreign_field '.$key,
            ]);
            $join->nested_joinable_relation()->associate($nested_join);
            $join->save();
            $item->joinable_relation()->associate($join);
            $item->save();
        });
        $response = $this->get(route('joinable_dummies.export'))
            ->assertStatus(200)
            ->assertDownload($expectedName);
        $this->assertExpectedSpreadsheetResponse($response, __DIR__.'/'.$expectedName);
    }
}
