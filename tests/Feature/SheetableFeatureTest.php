<?php

namespace Syspons\Sheetable\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Maatwebsite\Excel\Facades\Excel;
use Syspons\Sheetable\Exports\SheetsExport;
use Syspons\Sheetable\Tests\Models\Relation;
use Syspons\Sheetable\Tests\Models\WithRelationDummy;
use Syspons\Sheetable\Tests\Models\Simple;
use Syspons\Sheetable\Tests\TestCase;

/**
 *
 */
class SheetableFeatureTest extends TestCase
{
    public function test_simple_routes_exist(): void
    {
        $expectedRoutes = [
            'simples.import',
            'simples.export',
            'simples.template',
            'with_relation_dummies.import',
            'with_relation_dummies.export',
            'with_relation_dummies.template',
            'relations.import',
            'relations.export',
            'relations.template',
        ];
        $registeredRoutes = array_keys(Route::getRoutes()->getRoutesByName());
        foreach ($expectedRoutes as $route) {
            $this->assertContains($route, $registeredRoutes);
        }
    }

    public function test_exported_with_relation_dummies_sheet_has_correct_headings()
    {
        Excel::fake();

        $this->get('/api/export/with_relation_dummies')
            ->assertStatus(200);

        // Assert that the correct export is downloaded.
        Excel::assertDownloaded('with_relation_dummies.xlsx', function (SheetsExport $export) {
            // TODO $export->registerEvents()['Maatwebsite\Excel\Events\AfterSheet'](EVENT); IS NOT CALLED ??
            return
                in_array('id', $export->headings()) &&
                in_array('relation_main_id', $export->headings());
//                && in_array('relation_additional_id_1', $export->headings());
        });
    }

    public function test_exported_with_relation_dummies_sheet_has_correct_values()
    {
        Excel::fake();
        $withRelationDummies = WithRelationDummy::factory()->count(3)
            ->for(Relation::factory())
            ->has(Relation::factory()->count(3))
            ->create();

        $this->get('/api/export/with_relation_dummies')
            ->assertStatus(200);

        Excel::assertDownloaded('with_relation_dummies.xlsx', function (SheetsExport $export) use ($withRelationDummies) {
            // Assert that the correct export is downloaded.
            $downloadedWithRelationDummy = $export->collection()->first();
            $downloadedWithRelationDummy2 = $export->collection()[1];

            return
                $downloadedWithRelationDummy->id === $withRelationDummies[0]->id &&
                $downloadedWithRelationDummy->title === $withRelationDummies[0]->title &&
                $downloadedWithRelationDummy->description === $withRelationDummies[0]->description &&
                $downloadedWithRelationDummy2->id === $withRelationDummies[1]->id &&
                $downloadedWithRelationDummy2->title === $withRelationDummies[1]->title &&
                $downloadedWithRelationDummy2->description === $withRelationDummies[1]->description;
        });
    }

    public function test_exported_simple_sheet_has_correct_headings()
    {
        Excel::fake();

        $this->get('/api/export/simples')
            ->assertStatus(200);

        Excel::assertDownloaded('simples.xlsx', function (SheetsExport $export) {
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
        $dbSimple = Simple::factory()->create();
        $this->get('/api/export/simples')
            ->assertStatus(200);

        Excel::assertDownloaded('simples.xlsx', function (SheetsExport $export) use ($dbSimple) {
            // Assert that the correct export is downloaded.
            $downloadedSimple = $export->collection()->first();

            return
                $downloadedSimple->id === $dbSimple->id &&
                $downloadedSimple->firstname === $dbSimple->firstname &&
                $downloadedSimple->lastname === $dbSimple->lastname;
        });
    }

    public function test_exported_selected_values()
    {
        Excel::fake();
        $withRelationDummies = WithRelationDummy::factory()->count(4)
            ->for(Relation::factory())
            ->has(Relation::factory()->count(4))
            ->create();
        $ids = array_slice($withRelationDummies->pluck('id')->toArray(), 0, 2);


        $this->call('GET', route('with_relation_dummies.export'), [
            'ids' => $ids,
        ])->assertStatus(200);

        Excel::assertDownloaded('with_relation_dummies.xlsx', function (SheetsExport $export) use ($ids) {
            $ret = $export->collection();
            $plucked = $ret->pluck('id')->toArray();
            return $ret->count() === count($ids) && $plucked == $ids;
        });
    }

    public function test_import_simples(): void
    {
        Excel::fake();

        $file = new UploadedFile(
            __DIR__ . '/simples-upload.xlsx',
            'simples-upload.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $response = $this->postJson('/api/import/simples', [
            'file' => $file
        ]);

        $this->assertEquals(200, $response->getStatusCode());

        // TODO AJ does not work
//        Excel::assertImported('simples-upload.xlsx');
//        $this->assertDatabaseCount('simples', 10);
//        $this->assertDatabaseHas(
//            'simples',
//            ['firstname' => 'Rick', 'lastname' => 'Sanchez']
//        );
    }
}
