<?php

namespace Syspons\Sheetable\Tests\Feature\SelectTest;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

/**
 *
 */
class SelectTest extends SelectTestCase
{
    use RefreshDatabase;

    public function test_routes_exist(): void
    {
        $expectedRoutes = [
            'select_dummies.import',
            'select_dummies.export',
            'select_dummies.template',
        ];
        $registeredRoutes = array_keys(Route::getRoutes()->getRoutesByName());
        foreach ($expectedRoutes as $route) {
            $this->assertContains($route, $registeredRoutes);
        }
    }

    public function test_store_selected_file()
    {
        $expectedName = 'select_dummies.xlsx';
        SelectDummy::factory()->count(3)->create()->each(function ($item, $key) {
            $item->title = $item->title2 = 'test '.++$key;
            $item->save();
        });
        $response = $this->get(route('select_dummies.export', ['select' => ['wrong']]))
            ->assertJsonValidationErrorFor('select.0');
        $response = $this->get(route('select_dummies.export', ['select' => ['id', 'title', 'title2']]))
            ->assertStatus(200)
            ->assertDownload($expectedName);
        $this->assertExpectedSpreadsheetResponse($response, __DIR__.'/'.$expectedName);
    }
}
