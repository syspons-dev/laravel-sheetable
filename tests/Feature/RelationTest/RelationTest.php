<?php

namespace Syspons\Sheetable\Tests\Feature\RelationTest;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Maatwebsite\Excel\Facades\Excel;
use Syspons\Sheetable\Exports\SheetsExport;

/**
 *
 */
class RelationTest extends RelationTestCase
{
    use RefreshDatabase;

    public function test_routes_exist(): void
    {
        $expectedRoutes = [
            'with_relation_dummies.import',
            'with_relation_dummies.export',
            'with_relation_dummies.template',
        ];
        $registeredRoutes = array_keys(Route::getRoutes()->getRoutesByName());
        foreach ($expectedRoutes as $route) {
            $this->assertContains($route, $registeredRoutes);
        }
    }

    public function test_export_with_relation_dummies()
    {
        $expectedName = 'with_relation_dummies.xlsx';
        WithRelationDummy::createInstances();
        $response = $this->get(route('with_relation_dummies.export'))
            ->assertStatus(200)
            ->assertDownload($expectedName);
        $this->assertExpectedSpreadsheetResponse($response, __DIR__.'/'.$expectedName);
    }

    public function test_exported_selected_values()
    {
        $expectedName = 'with_relation_dummies.xlsx';
        // create 5, expect 3, ignore metadatasheet
        $withRelationDummies = WithRelationDummy::createInstances(5);
        $ids = array_slice($withRelationDummies->pluck('id')->toArray(), 0, 3);


        $response = $this->call('GET', route('with_relation_dummies.export'), [
            'ids' => $ids,
        ])->assertStatus(200)->assertDownload($expectedName);
        $this->assertExpectedSpreadsheetResponse($response, __DIR__.'/'.$expectedName, true, 0);
    }

    public function test_import_with_relation_dummies(): void
    {
        $file = new UploadedFile(
            __DIR__ . '/with_relation_dummies.xlsx',
            'with_relation_dummies.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $one = OneToManyRelation::factory()->create(['label' => 'one_to_many']);
        $count = 3;
        foreach (range(1, $count) as $key1) {
            foreach (range(1, $count) as $key2) {
                OneToManyRelation::factory()->create(['label' => 'many_to_many '.$key1.$key2]);
            }
        }

        $response = $this->postJson(route('with_relation_dummies.import'), [
            'file' => $file
        ])->assertStatus(200);
        $this->assertDatabaseCount('with_relation_dummies', 3);
        $this->assertDatabaseHas('with_relation_dummies', ['title' => 'test 1', 'description' => 'description 1', 'one_to_many_relation_id' => $one->id]);
        $this->assertDatabaseHas('with_relation_dummies', ['title' => 'test 2', 'description' => 'description 2', 'one_to_many_relation_id' => $one->id]);
        $this->assertDatabaseHas('with_relation_dummies', ['title' => 'test 3', 'description' => 'description 3', 'one_to_many_relation_id' => $one->id]);

        $this->assertDatabaseHas('many_to_many_relation_with_relation_dummy', ['with_relation_dummy_id' => 1, 'many_to_many_relation_id' => 1]);
        $this->assertDatabaseHas('many_to_many_relation_with_relation_dummy', ['with_relation_dummy_id' => 1, 'many_to_many_relation_id' => 2]);
        $this->assertDatabaseHas('many_to_many_relation_with_relation_dummy', ['with_relation_dummy_id' => 1, 'many_to_many_relation_id' => 3]);
        $this->assertDatabaseHas('many_to_many_relation_with_relation_dummy', ['with_relation_dummy_id' => 2, 'many_to_many_relation_id' => 4]);
        $this->assertDatabaseHas('many_to_many_relation_with_relation_dummy', ['with_relation_dummy_id' => 2, 'many_to_many_relation_id' => 5]);
        $this->assertDatabaseHas('many_to_many_relation_with_relation_dummy', ['with_relation_dummy_id' => 2, 'many_to_many_relation_id' => 6]);
        $this->assertDatabaseHas('many_to_many_relation_with_relation_dummy', ['with_relation_dummy_id' => 3, 'many_to_many_relation_id' => 7]);
        $this->assertDatabaseHas('many_to_many_relation_with_relation_dummy', ['with_relation_dummy_id' => 3, 'many_to_many_relation_id' => 8]);
        $this->assertDatabaseHas('many_to_many_relation_with_relation_dummy', ['with_relation_dummy_id' => 3, 'many_to_many_relation_id' => 9]);
    }

    public function test_import_with_relation_dummies_100(): void
    {
        $file = new UploadedFile(
            __DIR__ . '/with_relation_dummies_100.xlsx',
            'with_relation_dummies_100.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $one = OneToManyRelation::factory()->create(['label' => 'one_to_many']);
        $count = 3;
        foreach (range(1, $count) as $key1) {
            foreach (range(1, $count) as $key2) {
                OneToManyRelation::factory()->create(['label' => 'many_to_many '.$key1.$key2]);
            }
        }

        $response = $this->postJson(route('with_relation_dummies.import'), [
            'file' => $file
        ])->assertStatus(200);
        $this->assertDatabaseCount('with_relation_dummies', 100);
    }

    // Old test by Andreas
    public function test_relations()
    {
        $withRelationDummies = WithRelationDummy::createInstances();

        $this->assertEquals(3, count($withRelationDummies[0]->many_to_many_relations));
        $this->assertEquals(1, $withRelationDummies[0]->one_to_many_relation->id);
        $this->assertEquals(1, $withRelationDummies[0]->many_to_many_relations[0]->id);
        $this->assertEquals(2, $withRelationDummies[0]->many_to_many_relations[1]->id);
        $this->assertEquals(3, $withRelationDummies[0]->many_to_many_relations[2]->id);
        $this->assertEquals(1, $withRelationDummies[1]->one_to_many_relation->id);
        $this->assertEquals(4, $withRelationDummies[1]->many_to_many_relations[0]->id);
        $this->assertEquals(5, $withRelationDummies[1]->many_to_many_relations[1]->id);
        $this->assertEquals(6, $withRelationDummies[1]->many_to_many_relations[2]->id);
    }

    // Old test by Andreas
    public function test_with_relation_dummies_sheet_has_correct_headings()
    {
        Excel::fake();

        $this->get(route('with_relation_dummies.export'))
            ->assertStatus(200);

        // Assert that the correct export is downloaded.
        Excel::assertDownloaded('with_relation_dummies.xlsx', function (SheetsExport $export) {
            // TODO $export->registerEvents()['Maatwebsite\Excel\Events\AfterSheet'](EVENT); IS NOT CALLED ??
            return
                in_array('id', $export->headings()) &&
                in_array('one_to_many_relation_id', $export->headings());
//                && in_array('relation_additional_id_1', $export->headings());
        });
    }

    // Old test by Andreas
    public function test_exported_with_relation_dummies_sheet_has_correct_values()
    {
        Excel::fake();
        $withRelationDummies = WithRelationDummy::createInstances();

        $this->get(route('with_relation_dummies.export'))
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
}
