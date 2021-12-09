<?php

namespace Syspons\Sheetable\Tests\Feature;

use Illuminate\Support\Facades\Route;
use Maatwebsite\Excel\Facades\Excel;
use Syspons\Sheetable\Exports\SheetsExport;
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

//    public function test_import_users(): void
//    {
//        Excel::fake();
//
//        $response = $this->postJson('/api/users/import', [
//            'file' => new \Illuminate\Http\UploadedFile(
//                __DIR__ . '/users-upload.xlsx', '
//                users-upload.xlsx',
//                null, null, true),
//        ]);
//
//
//        $this->assertDatabaseHas('users', array_merge(
//            [
//                'firtname' => 'Rick',
//                'lastname' => 'Sanchez',
//            ]
//        ));
//    }
}
