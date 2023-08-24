<?php

namespace Syspons\Sheetable\Tests\Feature\JoinHasManyTest;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

/**
 *
 */
class JoinHasManyTest extends JoinHasManyTestCase
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

    public function test_store_joinable_has_many_dummy()
    {
        $expectedName = 'joinable_dummies.xlsx';
        JoinableDummy::factory()->count(3)->make()->each(function ($item, $index) {
            $key = $index + 1;
            $item->title = 'title '.$key;
            $item->description = 'description '.$key;
            $item->save();
            JoinableRelation::factory()->count(3)->make()->each(function ($relItem, $relIndex) use ($item, $index) {
                $relKey = $relIndex + 1 + ($index * 3);
                $relItem->foreign_field = 'foreign_field '.$relKey;
                $relItem->another_foreign_field = 'another_foreign_field '.$relKey;
                $relItem->joinable_dummy()->associate($item);
                $relItem->save();
            });
        });
        $response = $this->get(route('joinable_dummies.export'))
            ->assertStatus(200)
            ->assertDownload($expectedName);
        $this->assertExpectedSpreadsheetResponse($response, __DIR__.'/'.$expectedName);
    }
}
