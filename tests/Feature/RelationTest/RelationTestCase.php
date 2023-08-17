<?php

namespace Syspons\Sheetable\Tests\Feature\RelationTest;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Syspons\Sheetable\Tests\TestCase;

abstract class RelationTestCase extends TestCase
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
        Schema::create('one_to_many_relations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('label');
        });

        Schema::create('with_relation_dummies', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title');
            $table->string('description');

            $table->string('one_to_many_relation_id');
            $table->foreign('one_to_many_relation_id')
                ->references('id')
                ->on('one_to_many_relations')->onDelete('cascade');
        });

        Schema::create('many_to_many_relations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('label');
        });

        Schema::create('many_to_many_relation_with_relation_dummy', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('many_to_many_relation_id');
            $table->foreign('many_to_many_relation_id')
                ->references('id')
                ->on('many_to_many_relations')->onDelete('cascade');
            $table->bigInteger('with_relation_dummy_id');
            $table->foreign('with_relation_dummy_id')
                ->references('id')
                ->on('with_relation_dummies')->onDelete('cascade');
        });
    }
}
