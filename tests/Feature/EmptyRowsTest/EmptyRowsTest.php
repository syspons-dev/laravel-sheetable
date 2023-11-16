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
            'empty_rows.import',
            'empty_rows.export',
            'empty_rows.template',
        ];
        $registeredRoutes = array_keys(Route::getRoutes()->getRoutesByName());
        foreach ($expectedRoutes as $route) {
            $this->assertContains($route, $registeredRoutes);
        }
    }

    public function test_import_empty_rows(): void
    {
        $file = new UploadedFile(
            __DIR__ . '/empty_rows.xlsx',
            'empty_rows.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $response = $this->postJson(route('empty_rows.import'), [
            'file' => $file
        ])->assertStatus(200);
        $this->assertDatabaseCount('empty_rows', 1);
        $this->assertDatabaseHas('empty_rows', ['title' => 'test 1']);
    }

}
