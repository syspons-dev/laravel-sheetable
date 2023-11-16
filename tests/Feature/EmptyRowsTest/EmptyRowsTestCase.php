<?php

namespace Syspons\Sheetable\Tests\Feature\EmptyRowsTest;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Syspons\Sheetable\Tests\TestCase;

abstract class EmptyRowsTestCase extends TestCase
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
        Schema::create('simple_dummies', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title');
            $table->dateTime('date_time');
        });
    }
}
