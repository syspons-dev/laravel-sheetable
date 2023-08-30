<?php

namespace Syspons\Sheetable\Tests\Feature\JoinHasManyNestedTest;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

/**
 *
 */
class JoinHasManyNestedTest extends JoinHasManyNestedTestCase
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
        JoinableDummy::factory()->count(3)->create()->each(function ($item, $index) {
            $key = $index + 1;
            $item->title = 'title '.$key;
            $item->description = 'description '.$key;
            $item->save();
            JoinableRelation::factory()->count(2)->make()->each(function ($relItem, $relIndex) use ($item, $index) {
                $relKey = $index * 2 + $relIndex + 1;
                $relItem->foreign_field = 'foreign_field '.$relKey;
                $relItem->another_foreign_field = 'another_foreign_field '.$relKey;
                $relItem->joinable_dummy()->associate($item);
                $relItem->save();
                NestedJoinableRelation::factory()->count(2)->make()->each(function ($nestedItem, $nestedIndex) use ($relItem, $relIndex, $index) {
                    $nestedKey = $index * 4 + $relIndex * 2 + $nestedIndex + 1;
                    $nestedItem->foreign_field = 'foreign_field '.$nestedKey;
                    $nestedItem->another_foreign_field = 'another_foreign_field '.$nestedKey;
                    $nestedItem->joinable_relation()->associate($relItem);
                    $nestedItem->save();
                });
            });
        });
        $response = $this->get(route('joinable_dummies.export'))
            ->assertStatus(200)
            ->assertDownload($expectedName);
        $this->assertExpectedSpreadsheetResponse($response, __DIR__.'/'.$expectedName);
    }
}
