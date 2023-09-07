<?php

namespace Syspons\Sheetable\Tests\Feature\ExportMappingManyTest;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;

/**
 *
 */
class ExportMappingManyTest extends ExportMappingManyTestCase
{
    use RefreshDatabase;

    public function test_routes_exist(): void
    {
        $expectedRoutes = [
            'mapped_dummies.import',
            'mapped_dummies.export',
            'mapped_dummies.template',
        ];
        $registeredRoutes = array_keys(Route::getRoutes()->getRoutesByName());
        foreach ($expectedRoutes as $route) {
            $this->assertContains($route, $registeredRoutes);
        }
    }

    public function test_export_mapped_many_dummies()
    {
        $expectedName = 'mapped_dummies.xlsx';
        foreach(range(0, 2) as $i) {
            $mapped = MappedDummy::create();
            foreach(range(0, 1) as $ri) {
                $key = $i * 2 + $ri + 1;
                $joinable = JoinableRelation::create([
                    'first' => "first $key",
                    'second' => "second $key",
                    'translatable_first' => [
                        'en' => "translatable_first_en $key",
                        'de' => "translatable_first_de $key",
                    ],
                    'translatable_second' => [
                        'en' => "translatable_second_en $key",
                        'de' => "translatable_second_de $key",
                    ],
                    'mapped_dummy_id' => $mapped->id,
                ]);
                foreach(range(1, 2) as $ni) {
                    $nestedkey = $i * 4 + $ri * 2 + $ni;
                    JoinableNestedRelation::create([
                        'first' => "first $nestedkey",
                        'second' => "second $nestedkey",
                        'joinable_relation_id' => $joinable->id,
                    ]);
                }
            }
        }
        $response = $this->get(route('mapped_dummies.export'))
            ->assertStatus(200)
            ->assertDownload($expectedName);
        $this->assertExpectedSpreadsheetResponse($response, __DIR__.'/'.$expectedName);
    }
}
