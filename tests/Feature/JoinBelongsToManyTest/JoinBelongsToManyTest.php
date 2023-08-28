<?php

namespace Syspons\Sheetable\Tests\Feature\JoinBelongsToManyTest;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

/**
 *
 */
class JoinBelongsToManyTest extends JoinBelongsToManyTestCase
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

    public function test_store_joinable_belongs_to_many_export()
    {
        $expectedName = 'joinable_dummies.xlsx';

        JoinableRelation::factory()->count(7)->create()->each(function ($relItem, $relIndex) {
            $relKey = $relIndex + 1;
            $relItem->foreign_field = 'foreign_field '.$relKey;
            $relItem->another_foreign_field = 'another_foreign_field '.$relKey;
            $relItem->save();
        });
        JoinableDummy::factory()->count(3)->create()->each(function ($item, $index) {
            $key = $index + 1;
            $relKey = 1 + $index * 2;
            $item->title = 'title '.$key;
            $item->description = 'description '.$key;
            foreach(range(0, 2) as $add) {
                $item->joinable_relations()->attach(JoinableRelation::find($relKey + $add));
            }
            $item->save();
        });
        $response = $this->get(route('joinable_dummies.export'))
            ->assertStatus(200)
            ->assertDownload($expectedName);
        $this->assertExpectedSpreadsheetResponse($response, __DIR__.'/'.$expectedName);
    }
}
