<?php

namespace Syspons\Sheetable\Tests\Feature\ImportTest;

use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use Syspons\Sheetable\Tests\Models\OneToManyRelation;
use Syspons\Sheetable\Tests\TestCase;

/**
 *
 */
class ImportTest extends TestCase
{
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
}
