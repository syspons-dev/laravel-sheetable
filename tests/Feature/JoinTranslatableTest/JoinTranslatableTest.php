<?php

namespace Syspons\Sheetable\Tests\Feature\JoinTranslatableTest;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

/**
 *
 */
class JoinTranslatableTest extends JoinTranslatableTestCase
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

    public function test_store_joinable_translation_export()
    {
        $expectedName = 'joinable_dummies.xlsx';

        $many_to_many = ManyToManyTranslatableDummy::factory()->count(4)->create()->each(function ($item, $key) {
            $item->title = [
                'de' => 'german '.++$key,
                'en' => 'english '.$key,
            ];
            $item->save();
        });

        JoinableDummy::factory()->count(3)->make()->each(function ($item, $index) {
            $key = $index + 1;
            $item->title = 'title '.$key;
            $item->description = 'description '.$key;
            $join = OneToManyTranslatableDummy::factory()->create([
                'title' => [
                    'de' => 'german '.$key,
                    'en' => 'english '.$key,
                ],
            ]);
            $item->one_to_many_translatable_dummy()->associate($join);
            $item->save();
            ManyToOneTranslatableDummy::factory()->count(2)->make()->each(function ($relItem, $relIndex) use ($item, $index) {
                $relKey = $relIndex + 1 + ($index * 2);
                $relItem->title = [
                    'de' => 'german '.$relKey,
                    'en' => 'english '.$relKey,
                ];
                $relItem->joinable_dummy()->associate($item);
                $relItem->save();
            });
            foreach(range(0, 1) as $add) {
                $item->many_to_many_translatable_dummies()->attach(ManyToManyTranslatableDummy::find($key + $add));
            }
        });
        $response = $this->get(route('joinable_dummies.export'))
            ->assertStatus(200)
            ->assertDownload($expectedName);
        $this->assertExpectedSpreadsheetResponse($response, __DIR__.'/'.$expectedName);
    }
}
