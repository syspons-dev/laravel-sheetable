<?php

namespace Syspons\Sheetable\Tests\Feature\ExportMappingManyTest;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Syspons\Sheetable\Tests\TestCase;

abstract class ExportMappingManyTestCase extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
    }

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);
        Config::set('translatable.languages', ['en' => 'English', 'de' => 'German']);
        Config::set('translatable.namespace', __NAMESPACE__);
        Config::set('sheetable.namespace', __NAMESPACE__);
        $this->setupTables();
    }

    private function setupTables(): void
    {
        Schema::create('joinable_nested_relations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('first');
            $table->string('second');

            $table->foreignId('joinable_relation_id')->constrained();
        });

        Schema::create('joinable_relations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('first');
            $table->string('second');
            $table->translatable('translatable_first');
            $table->translatable('translatable_second');

            $table->foreignId('mapped_dummy_id')->constrained();
        });

        Schema::create('mapped_dummies', function (Blueprint $table) {
            $table->bigIncrements('id');
        });
    }
}
