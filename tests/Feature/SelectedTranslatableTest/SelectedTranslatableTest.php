<?php

namespace Syspons\Sheetable\Tests\Feature\SelectedTranslatableTest;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 *
 */
class SelectedTranslatableTest extends SelectedTranslatableTestCase
{
    use RefreshDatabase;

    public function test_routes_exist(): void
    {
        $expectedRoutes = [
            'translatable_dummies.import',
            'translatable_dummies.export',
            'translatable_dummies.template',
        ];
        $registeredRoutes = array_keys(Route::getRoutes()->getRoutesByName());
        foreach ($expectedRoutes as $route) {
            $this->assertContains($route, $registeredRoutes);
        }
    }

    public function test_store_translatable_dummy()
    {
        $this->assertTrue(Schema::hasTable('translatable_contents'));

        $expectedName = 'translatable_dummies.xlsx';
        TranslatableDummy::factory()->count(3)->create()->each(function ($item, $key) {
            $index = ++$key;
            $item->title = [
                'de' => 'german '.$index,
                'en' => 'english '.$index,
            ];
            $item->save();
        });
        $response = $this->get(route('translatable_dummies.export', [
            'lang' => 'de',
        ]))
            ->assertStatus(200)
            ->assertDownload($expectedName);
        $this->assertExpectedSpreadsheetResponse($response, __DIR__.'/'.$expectedName);
    }
}
