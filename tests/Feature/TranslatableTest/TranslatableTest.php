<?php

namespace Syspons\Sheetable\Tests\Feature\TranslatableTest;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 *
 */
class TranslatableTest extends TranslatableTestCase
{
    use RefreshDatabase;

    public function test_routes_exist(): void
    {
        $expectedRoutes = [
            'translatable_dummies.import',
            'translatable_dummies.export',
            'translatable_dummies.template',
        ];
        $registeredRoutes = array_keys(Route::getRoutes()->getRoutesByName());
        foreach ($expectedRoutes as $route) {
            $this->assertContains($route, $registeredRoutes);
        }
    }

    public function test_store_translatable_dummy()
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
