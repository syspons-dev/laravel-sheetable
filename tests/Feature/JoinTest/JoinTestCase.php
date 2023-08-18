<?php

namespace Syspons\Sheetable\Tests\Feature\JoinTest;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Syspons\Sheetable\Tests\TestCase;

abstract class JoinTestCase extends TestCase
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
        Schema::create('joinable_relations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('foreign_field');
            $table->string('another_foreign_field');
        });

        Schema::create('nested_joinable_relations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('foreign_field');
            $table->string('another_foreign_field');
        });

        Schema::create('another_joinable_relations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('foreign_field');
            $table->string('another_foreign_field');            
            
            $table->foreignId('nested_joinable_relation_id')->constrained();

        });

        Schema::create('joinable_dummies', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title');
            $table->string('description');

            $table->foreignId('joinable_relation_id')->constrained();
            $table->foreignId('another_joinable_relation_id')->constrained();
        });
    }
}
