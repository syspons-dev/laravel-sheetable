<?php

namespace Syspons\Sheetable\Tests\Feature\ExportMappingTest;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Syspons\Sheetable\Tests\TestCase;

abstract class ExportMappingTestCase extends TestCase
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
        Schema::create('mapped_dummies', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title');
            $table->string('one');
            $table->string('two');
            $table->string('first_name');
            $table->string('last_name');
            $table->dateTime('date_time_start');
            $table->dateTime('date_time_end');
        });
    }
}
