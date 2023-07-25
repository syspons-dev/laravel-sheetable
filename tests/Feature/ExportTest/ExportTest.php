<?php

namespace Syspons\Sheetable\Tests\Feature\ExportTest;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Facades\Excel;
use Syspons\Sheetable\Exports\SheetsExport;
use Syspons\Sheetable\Tests\Models\ScopeableManyToManyRelation;
use Syspons\Sheetable\Tests\Models\WithRelationDummy;
use Syspons\Sheetable\Tests\Models\SimpleDummy;
use Syspons\Sheetable\Tests\Models\TranslatableDummy;
use Syspons\Sheetable\Tests\Models\User;
use Syspons\Sheetable\Tests\Models\WithScopeableRelationDummy;
use Syspons\Sheetable\Tests\TestCase;

/**
 *
 */
class ExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_simple_file()
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

    public function test_store_with_relation_file()
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

    public function test_exported_scopeable_values()
    {
        $expectedName = 'with_scopeable_relation_dummies.xlsx';
        // create 6, expect 3, ignore metadatasheet
        $scropableAllowed = ScopeableManyToManyRelation::createInstance();
        $scropableNotAllowed = ScopeableManyToManyRelation::createInstance();
        $user = User::factory()->hasAttached($scropableAllowed, [], 'scopeable_many_to_many_relations')->create();

        WithScopeableRelationDummy::createInstances(3)->each(function($instance) use ($scropableAllowed) {
            $instance->scopeable_many_to_many_relations()->attach($scropableAllowed);
        });
        WithScopeableRelationDummy::createInstances(3)->each(function($instance) use ($scropableNotAllowed) {
            $instance->scopeable_many_to_many_relations()->attach($scropableNotAllowed);
        });
        
        $this->actingAs($user);

        $response = $this->call('GET', route('with_scopeable_relation_dummies.export'))
            ->assertStatus(200)
            ->assertDownload($expectedName);
        $this->assertExpectedSpreadsheetResponse($response, __DIR__.'/'.$expectedName, true, 0);
    }

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

    public function test_store_translatable_file()
    {
        $this->assertTrue(Schema::hasTable('translatable_contents'));

        $expectedName = 'translatable_dummies.xlsx';
        TranslatableDummy::factory()->count(3)->create()->each(function ($item, $key) {
            $index = ++$key;
            $item->title = [
                'de' => 'german '.$index,
                'en' => 'english '.$index,
            ];
            $item->save();
        });
        $response = $this->get(route('translatable_dummies.export'))
            ->assertStatus(200)
            ->assertDownload($expectedName);
        $this->assertExpectedSpreadsheetResponse($response, __DIR__.'/'.$expectedName);
    }
}
