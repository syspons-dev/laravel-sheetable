<?php

namespace Syspons\Sheetable\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Maatwebsite\Excel\Facades\Excel;
use Syspons\Sheetable\Exports\SheetsExport;
use Syspons\Sheetable\Tests\Country;
use Syspons\Sheetable\Tests\ModelDummy;
use Syspons\Sheetable\Tests\TestCase;
use Syspons\Sheetable\Tests\User;

/**
 *
 */
class SheetableFeatureTest extends TestCase
{
    public function test_user_routes_exist(): void
    {
        $expectedRoutes = [
            'users.import',
            'users.export',
            'users.template',
            'model_dummies.import',
            'model_dummies.export',
            'model_dummies.template',
            'countries.import',
            'countries.export',
            'countries.template',
        ];
        $registeredRoutes = array_keys(Route::getRoutes()->getRoutesByName());
        foreach ($expectedRoutes as $route) {
            $this->assertContains($route, $registeredRoutes);
        }
    }

    public function test_exported_model_dummies_sheet_has_correct_headings()
    {
        Excel::fake();

        $this->get('/api/export/model_dummies')
            ->assertStatus(200);

        // Assert that the correct export is downloaded.
        Excel::assertDownloaded('model_dummies.xlsx', function (SheetsExport $export) {
            // TODO $export->registerEvents()['Maatwebsite\Excel\Events\AfterSheet'](EVENT); IS NOT CALLED ??
            return
                in_array('id', $export->headings()) &&
                in_array('country_main_id', $export->headings());
//                && in_array('country_additional_id_1', $export->headings());
        });
    }

    public function test_exported_model_dummies_sheet_has_correct_values()
    {
        Excel::fake();
        $modelDummies = ModelDummy::factory()->count(3)
            ->for(Country::factory())
            ->has(Country::factory()->count(3))
            ->create();

        $this->get('/api/export/model_dummies')
            ->assertStatus(200);

        Excel::assertDownloaded('model_dummies.xlsx', function (SheetsExport $export) use ($modelDummies) {
            // Assert that the correct export is downloaded.
            $downloadedModelDummy = $export->collection()->first();
            $downloadedModelDummy2 = $export->collection()[1];

            return
                $downloadedModelDummy->id === $modelDummies[0]->id &&
                $downloadedModelDummy->title === $modelDummies[0]->title &&
                $downloadedModelDummy->description === $modelDummies[0]->description &&
                $downloadedModelDummy2->id === $modelDummies[1]->id &&
                $downloadedModelDummy2->title === $modelDummies[1]->title &&
                $downloadedModelDummy2->description === $modelDummies[1]->description;
        });
    }

    public function test_exported_user_sheet_has_correct_headings()
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

    public function test_exported_selected_values()
    {
        Excel::fake();
        $modelDummies = ModelDummy::factory()->count(4)
            ->for(Country::factory())
            ->has(Country::factory()->count(4))
            ->create();
        $ids = array_slice($modelDummies->pluck('id')->toArray(), 0, 2);


        $this->call('GET', route('model_dummies.export'), [
            'ids' => $ids,
        ])->assertStatus(200);

        Excel::assertDownloaded('model_dummies.xlsx', function (SheetsExport $export) use ($ids) {
            $ret = $export->collection();
            $plucked = $ret->pluck('id')->toArray();
            return $ret->count() === count($ids) && $plucked == $ids;
        });
    }

    public function test_import_users(): void
    {
        Excel::fake();

        $file = new UploadedFile(
            __DIR__ . '/users-upload.xlsx',
            'users-upload.xlsx',
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
//        $this->assertDatabaseCount('users', 10);
//        $this->assertDatabaseHas(
//            'users',
//            ['firstname' => 'Rick', 'lastname' => 'Sanchez']
//        );
    }
}
