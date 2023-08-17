<?php

namespace Syspons\Sheetable\Tests\Feature\BasicTest;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Maatwebsite\Excel\Facades\Excel;
use Syspons\Sheetable\Exports\SheetsExport;

/**
 *
 */
class BasicTest extends BasicTestCase
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

    public function test_export_simple_dummies()
    {
        $expectedName = 'simple_dummies.xlsx';
        SimpleDummy::factory()->count(3)->create()->each(function ($item, $key) {
            $item->title = 'test '.++$key;
            $item->save();
        });
        $response = $this->get(route('simple_dummies.export'))
            ->assertStatus(200)
            ->assertDownload($expectedName);
        $this->assertExpectedSpreadsheetResponse($response, __DIR__.'/'.$expectedName);
    }

    public function test_import_simple_dummies(): void
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
        $this->assertDatabaseCount('simple_dummies', 3);
        $this->assertDatabaseHas('simple_dummies', ['title' => 'test 1']);
        $this->assertDatabaseHas('simple_dummies', ['title' => 'test 2']);
        $this->assertDatabaseHas('simple_dummies', ['title' => 'test 3']);
    }

    public function test_import_simple_dummies_100(): void
    {
        $file = new UploadedFile(
            __DIR__ . '/simple_dummies_100.xlsx',
            'simple_dummies_100.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $response = $this->postJson(route('simple_dummies.import'), [
            'file' => $file
        ])->assertStatus(200);
        $this->assertDatabaseCount('simple_dummies', 100);
    }

    // Old test by Andreas
    public function test_exported_simpleDummy_sheet_has_correct_headings()
    {
        Excel::fake();

        $this->get(route('simple_dummies.export'))
            ->assertStatus(200);

        Excel::assertDownloaded('simple_dummies.xlsx', function (SheetsExport $export) {
            // Assert that the correct export is downloaded.

            return
                in_array('id', $export->headings()) &&
                in_array('title', $export->headings());
        });
    }

    // Old test by Andreas
    public function test_exported_sheet_has_correct_values()
    {
        Excel::fake();
        $dbSimpleDummy = SimpleDummy::factory()->create();
        $this->get(route('simple_dummies.export'))
            ->assertStatus(200);

        Excel::assertDownloaded('simple_dummies.xlsx', function (SheetsExport $export) use ($dbSimpleDummy) {
            // Assert that the correct export is downloaded.
            $downloadedSimpleDummy = $export->collection()->first();

            return
                $downloadedSimpleDummy->id === $dbSimpleDummy->id &&
                $downloadedSimpleDummy->title === $dbSimpleDummy->title;
        });
    }
}
