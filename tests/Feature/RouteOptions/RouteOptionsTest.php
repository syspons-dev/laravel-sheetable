<?php

namespace Syspons\Sheetable\Tests\Feature\RouteOptions;

use Illuminate\Support\Facades\Route;
use Syspons\Sheetable\Tests\TestCase;

class RouteOptionsTest extends TestCase
{
    public function test_only_routes(): void
    {
        $expectedRoutes = [
            'has_onlies.export',
        ];
        $unexpectedRoutes = [
            'has_onlies.import',
            'has_onlies.template',
        ];
        $registeredRoutes = array_keys(Route::getRoutes()->getRoutesByName());
        foreach ($expectedRoutes as $route) {
            $this->assertContains($route, $registeredRoutes);
        }
        foreach ($unexpectedRoutes as $route) {
            $this->assertNotContains($route, $registeredRoutes);
        }
    }

    public function test_except_routes(): void
    {
        $expectedRoutes = [
            'has_excepts.export',
            'has_excepts.template',
        ];
        $unexpectedRoutes = [
            'has_excepts.import',
        ];
        $registeredRoutes = array_keys(Route::getRoutes()->getRoutesByName());
        foreach ($expectedRoutes as $route) {
            $this->assertContains($route, $registeredRoutes);
        }
        foreach ($unexpectedRoutes as $route) {
            $this->assertNotContains($route, $registeredRoutes);
        }
    }
}
