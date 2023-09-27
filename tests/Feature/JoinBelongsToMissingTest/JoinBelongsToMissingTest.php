<?php

namespace Syspons\Sheetable\Tests\Feature\JoinBelongsToMissingTest;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

/**
 *
 */
class JoinBelongsToMissingTest extends JoinBelongsToMissingTestCase
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
        foreach(range(1, 3) as $key) {
            $relation = JoinableRelation::create([
                'foreign_field' => "foreign_field $key",
                'another_foreign_field' => "another_foreign_field $key",
            ]);
            $dummy = JoinableDummy::create([
                'title' => "title $key",
                'description' => "description $key",
                ...($key != 3 ? ['joinable_relation_id' => $relation->id] : []),
            ]);
        }
        $response = $this->get(route('joinable_dummies.export'))
            ->assertStatus(200)
            ->assertDownload($expectedName);
        $this->assertExpectedSpreadsheetResponse($response, __DIR__.'/'.$expectedName);
    }
}
