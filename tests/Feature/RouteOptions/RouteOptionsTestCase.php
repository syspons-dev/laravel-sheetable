<?php

namespace Syspons\Sheetable\Tests\Feature\RouteOptions;

use Illuminate\Support\Facades\Config;
use Syspons\Sheetable\Tests\TestCase;

abstract class RouteOptionsTestCase extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
    }

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);
        Config::set('sheetable.namespace', __NAMESPACE__);
        $this->setupTables();
    }

    private function setupTables(): void
    {
        
    }
}
