<?php

namespace Syspons\Sheetable\Tests\Feature\ExportMappingTranslatableTest;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;

/**
 *
 */
class ExportMappingTranslatableTest extends ExportMappingTranslatableTestCase
{
    use RefreshDatabase;

    public function test_routes_exist(): void
    {
        $expectedRoutes = [
            'mapped_dummies.import',
            'mapped_dummies.export',
            'mapped_dummies.template',
        ];
        $registeredRoutes = array_keys(Route::getRoutes()->getRoutesByName());
        foreach ($expectedRoutes as $route) {
            $this->assertContains($route, $registeredRoutes);
        }
    }

    public function test_export_mapped_translatable_dummies()
    {
        $expectedName = 'mapped_dummies.xlsx';
        foreach(range(1, 3) as $i) {
            $item = MappedDummy::make();
            $item->title = [
                'en' => "title_en $i",
                'de' => "title_de $i",
            ];
            $item->first = [
                'en' => "first_en $i",
                'de' => "first_de $i",
            ];
            $item->second = [
                'en' => "second_en $i",
                'de' => "second_de $i",
            ];
            $item->save();
        }
        $response = $this->get(route('mapped_dummies.export'))
            ->assertStatus(200)
            ->assertDownload($expectedName);
        $this->assertExpectedSpreadsheetResponse($response, __DIR__.'/'.$expectedName);
    }
}
