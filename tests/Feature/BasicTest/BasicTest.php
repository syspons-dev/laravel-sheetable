<?php

namespace Syspons\Sheetable\Tests\Feature\BasicTest;

use Illuminate\Support\Facades\Route;
use Syspons\Sheetable\Tests\TestCase;

/**
 *
 */
class BasicTest extends TestCase
{
    public function test_simpleDummy_routes_exist(): void
    {
        $expectedRoutes = [
            'simple_dummies.import',
            'simple_dummies.export',
            'simple_dummies.template',
            'with_relation_dummies.import',
            'with_relation_dummies.export',
            'with_relation_dummies.template',
        ];
        $registeredRoutes = array_keys(Route::getRoutes()->getRoutesByName());
        foreach ($expectedRoutes as $route) {
            $this->assertContains($route, $registeredRoutes);
        }
    }
}
