<?php

namespace Syspons\Sheetable\Tests;

use Carbon\Carbon;
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
        $this->setupTables();
        Config::set('sheetable.namespace', __NAMESPACE__);
    }

    private function setupTables(): void
    {
        Schema::create('simples', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('firstname');
            $table->string('lastname');
            $table->timestamps();
        });

        Schema::create('model_dummies', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title');
            $table->string('description');
            $table->string('country_main_id');
            $table->timestamps();
        });

        Schema::create('countries', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('label');
            $table->timestamps();
        });

        Schema::create('country_model_dummy', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('country_id');
            $table->bigInteger('model_dummy_id');
            $table->timestamps();
        });
    }
}
