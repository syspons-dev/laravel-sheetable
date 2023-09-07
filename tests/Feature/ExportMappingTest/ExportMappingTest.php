<?php

namespace Syspons\Sheetable\Tests\Feature\ExportMappingTest;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;

/**
 *
 */
class ExportMappingTest extends ExportMappingTestCase
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

    public function test_export_mapped_dummies()
    {
        $expectedName = 'mapped_dummies.xlsx';
        foreach(range(1, 3) as $i) {
            MappedDummy::create([
                'title' => "title $i",
                'one' => "one $i",
                'two' => "two $i",
                'first_name' => "first_name $i",
                'last_name' => "last_name $i",
                'date_time_start' => '2020-01-01',
                'date_time_end' => '2021-01-01',
            ]);
        }
        $response = $this->get(route('mapped_dummies.export'))
            ->assertStatus(200)
            ->assertDownload($expectedName);
        $this->assertExpectedSpreadsheetResponse($response, __DIR__.'/'.$expectedName);
    }
}
