<?php

namespace Syspons\Sheetable\Tests\Feature\EmptyRowsTest;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Maatwebsite\Excel\Facades\Excel;
use Syspons\Sheetable\Exports\SheetsExport;

/**
 *
 */
class EmptyRowsTest extends EmptyRowsTestCase
{
    use RefreshDatabase;

    public function test_routes_exist(): void
    {
        $expectedRoutes = [
            'simple_dummies.import',
            'simple_dummies.export',
            'simple_dummies.template',
        ];
        $registeredRoutes = array_keys(Route::getRoutes()->getRoutesByName());
        foreach ($expectedRoutes as $route) {
            $this->assertContains($route, $registeredRoutes);
        }
    }

    public function test_import_empty_rows(): void
    {
        $file = new UploadedFile(
            __DIR__ . '/simple_dummies.xlsx',
            'simple_dummies.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $response = $this->postJson(route('simple_dummies.import'), [
            'file' => $file
        ])->assertStatus(200);
        $this->assertDatabaseCount('simple_dummies', 1);
        $this->assertDatabaseHas('simple_dummies', ['title' => 'test 1']);
    }

}
