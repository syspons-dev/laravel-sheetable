<?php

namespace Syspons\Sheetable\Tests\Feature\JoinMorphToTest;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

/**
 *
 */
class JoinMorphToTest extends JoinMorphToTestCase
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

    // when not in debug causes a segmentation fault
    public function test_store_joinable_morph_file()
    {
        $expectedName = 'joinable_dummies.xlsx';
        foreach(range(1,3) as $key) {
            $item = JoinableDummy::create([
                'title' => 'title '.$key,
                'description' => 'description '.$key,
            ]);
            $join = JoinableRelation::factory()->make([
                'foreign_field' => 'foreign_field '.$key,
                'another_foreign_field' => 'another_foreign_field '.$key,
            ]);
            $join->joinable_relatable()->associate($item);
            $join->save();
        }
        $response = $this->get(route('joinable_dummies.export'))
            ->assertStatus(200)
            ->assertDownload($expectedName);
        $this->assertExpectedSpreadsheetResponse($response, __DIR__.'/'.$expectedName);
    }
}
