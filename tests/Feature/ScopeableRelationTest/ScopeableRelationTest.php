<?php

namespace Syspons\Sheetable\Tests\Feature\ScopeableRelationTest;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;

/**
 *
 */
class ScopeableRelationTest extends ScopeableRelationTestCase
{
    use RefreshDatabase;

    public function test_routes_exist(): void
    {
        $expectedRoutes = [
            'with_scopeable_relation_dummies.import',
            'with_scopeable_relation_dummies.export',
            'with_scopeable_relation_dummies.template',
        ];
        $registeredRoutes = array_keys(Route::getRoutes()->getRoutesByName());
        foreach ($expectedRoutes as $route) {
            $this->assertContains($route, $registeredRoutes);
        }
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
}
