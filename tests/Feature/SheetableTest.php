<?php

namespace Syspons\Sheetable\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Maatwebsite\Excel\Facades\Excel;
use Mockery;
use Syspons\Sheetable\Exports\SheetsExport;
use Syspons\Sheetable\Helpers\SpreadsheetDropdowns;
use Syspons\Sheetable\Helpers\SpreadsheetHelper;
use Syspons\Sheetable\Helpers\SpreadsheetUtils;
use Syspons\Sheetable\Tests\TestCase;
use Syspons\Sheetable\Tests\User;

/**
 *
 */
class SheetableTest extends TestCase
{
    public function test_user_routes_exist(): void
    {
        $expectedRoutes = [
            'users.import',
            'users.export',
            'users.template',
        ];
        $registeredRoutes = array_keys(Route::getRoutes()->getRoutesByName());
        foreach ($expectedRoutes as $route) {
            $this->assertContains($route, $registeredRoutes);
        }
    }

    public function test_exported_sheet_has_correct_headings()
    {
        Excel::fake();

        $this->get('/api/export/users')
            ->assertStatus(200);

        Excel::assertDownloaded('users.xlsx', function (SheetsExport $export) {
            // Assert that the correct export is downloaded.
            return
                in_array('id', $export->headings()) &&
                in_array('firstname', $export->headings()) &&
                in_array('lastname', $export->headings());
        });
    }

    public function test_exported_sheet_has_correct_values()
    {
        Excel::fake();
        $dbUser = User::factory()->create();
        $this->get('/api/export/users')
            ->assertStatus(200);

        Excel::assertDownloaded('users.xlsx', function (SheetsExport $export) use ($dbUser) {
            // Assert that the correct export is downloaded.
            $downloadedUser = $export->collection()->first();

            return
                $downloadedUser->id === $dbUser->id &&
                $downloadedUser->firstname === $dbUser->firstname &&
                $downloadedUser->lastname === $dbUser->lastname;
        });
    }

    public function test_import_users(): void
    {
        Excel::fake();

        $file = new UploadedFile(
            __DIR__ . '/users-upload.xlsx', '
                users-upload.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $response = $this->postJson('/api/import/users', [
            'file' => $file
        ]);

        $this->assertEquals(200, $response->getStatusCode());

        // TODO AJ does not work
//        Excel::assertImported('users-upload.xlsx');
//
//        $this->assertDatabaseCount('users', 10);
//        $this->assertDatabaseHas(
//            'users',
//            ['firstname' => 'Rick', 'lastname' => 'Sanchez']
//        );
    }

    public function  testUpdateOrCreate(){


        $utilsMock = Mockery::mock(SpreadsheetUtils::class);
        $dropdownsMock = Mockery::mock(SpreadsheetDropdowns::class);

        $spreadsheetHelper = new SpreadsheetHelper($utilsMock, $dropdownsMock);

        $rowArr = [
            'id' => 1,
            'fistname' => 'Rick',
            'lastname' => 'Sanchez',
        ];

        $this->assertNotNull($spreadsheetHelper);
        // TODO
//        $spreadsheetHelper->updateOrCreate($rowArr, User::class);
//        $this->assertCount(1, User::all());
//        $this->assertDatabaseCount('users', 1);
//        $this->assertDatabaseHas('users', ['firstname' => 'Rick'] );
        
    }
}
