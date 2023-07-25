<?php

namespace Syspons\Sheetable\Tests\Feature\ImportTest;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Syspons\Sheetable\Tests\Models\OneToManyRelation;
use Syspons\Sheetable\Tests\Models\ScopeableManyToManyRelation;
use Syspons\Sheetable\Tests\Models\User;
use Syspons\Sheetable\Tests\Models\WithScopeableRelationDummy;
use Syspons\Sheetable\Tests\TestCase;

/**
 *
 */
class ImportTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_import_with_scopable_relation_dummies_success(): void
    {
        $file = new UploadedFile(
            __DIR__ . '/with_scopeable_relation_dummies_success.xlsx',
            'with_scopeable_relation_dummies_success.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $scopeableAllowed = ScopeableManyToManyRelation::createInstance();
        $scopeableNotAllowed = ScopeableManyToManyRelation::createInstance();

        $user = User::factory()->hasAttached($scopeableAllowed, [], 'scopeable_many_to_many_relations')->create();
        $this->actingAs($user);

        $response = $this->postJson(route('with_scopeable_relation_dummies.import'), [
            'file' => $file
        ])->assertStatus(200);
        $this->assertDatabaseCount('with_scopeable_relation_dummies', 3);
        $this->assertDatabaseHas('with_scopeable_relation_dummies', ['title' => 'test 1', 'description' => 'description 1']);
        $this->assertDatabaseHas('with_scopeable_relation_dummies', ['title' => 'test 2', 'description' => 'description 2']);
        $this->assertDatabaseHas('with_scopeable_relation_dummies', ['title' => 'test 3', 'description' => 'description 3']);

        $this->assertDatabaseHas('scopeable_many_to_many_relation_with_scopeable_relation_dummy', ['with_scopeable_relation_dummy_id' => 1, 'scopeable_many_to_many_relation_id' => 1]);
        $this->assertDatabaseHas('scopeable_many_to_many_relation_with_scopeable_relation_dummy', ['with_scopeable_relation_dummy_id' => 2, 'scopeable_many_to_many_relation_id' => 1]);
        $this->assertDatabaseHas('scopeable_many_to_many_relation_with_scopeable_relation_dummy', ['with_scopeable_relation_dummy_id' => 3, 'scopeable_many_to_many_relation_id' => 1]);
    }

    public function test_import_with_scopable_relation_dummies_failure(): void
    {
        $file = new UploadedFile(
            __DIR__ . '/with_scopeable_relation_dummies_failure.xlsx',
            'with_scopeable_relation_dummies_failure.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $scopeableAllowed = ScopeableManyToManyRelation::createInstance();
        $scopeableNotAllowed = ScopeableManyToManyRelation::createInstance();

        // expected count in table
        WithScopeableRelationDummy::createInstances(2);

        $user = User::factory()->hasAttached($scopeableAllowed, [], 'scopeable_many_to_many_relations')->create();
        $this->actingAs($user);

        //$this->expectException(ExcelImportScopeableException::class);
        // not thrown as it's transformed to a response
        $response = $this->postJson(route('with_scopeable_relation_dummies.import'), [
            'file' => $file
        ])->assertStatus(422);
        $this->assertDatabaseCount('with_scopeable_relation_dummies', 2);
        $this->assertDatabaseCount('scopeable_many_to_many_relation_with_scopeable_relation_dummy', 0);
    }

    public function test_import_translatable_dummies(): void
    {
        $this->assertTrue(Schema::hasTable('translatable_contents'));

        $file = new UploadedFile(
            __DIR__ . '/translatable_dummies.xlsx',
            'translatable_dummies.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $response = $this->postJson(route('translatable_dummies.import'), [
            'file' => $file
        ])->assertStatus(200);
        $this->assertDatabaseCount('translatable_dummies', 3);
        $this->assertDatabaseHas('translatable_dummies', ['title_translatable_content_id' => '1']);
        $this->assertDatabaseHas('translatable_contents', [
            'id' => '1',
            'language' => 'en',
            'text' => 'english 1',
        ]);
        $this->assertDatabaseHas('translatable_translations', [
            'id' => '1',
            'translatable_content_id' => '1',
            'language' => 'de',
            'text' => 'german 1',
        ]);
        $this->assertDatabaseHas('translatable_dummies', ['title_translatable_content_id' => '2']);
        $this->assertDatabaseHas('translatable_dummies', ['title_translatable_content_id' => '3']);
    }
}
