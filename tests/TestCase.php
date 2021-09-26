<?php

namespace Syspons\Sheetable\Tests;

use Syspons\Sheetable\SheetableServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{

    public function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app): array
    {
        return [
            SheetableServiceProvider::class
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $this->setUpUserTable();
        Config::set('sheetable.namespace', 'Syspons\Sheetable\Tests');
    }

    private function setUpUserTable(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('firstname');
            $table->string('lastname');
            $table->timestamps();
        });
    }
}
