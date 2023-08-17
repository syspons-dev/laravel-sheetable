<?php

namespace Syspons\Sheetable\Tests\Feature\ScopeableRelationTest;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Syspons\Sheetable\Tests\TestCase;

abstract class ScopeableRelationTestCase extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
    }

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);
        Config::set('sheetable.namespace', __NAMESPACE__);
        Config::set('scopeable.namespace', __NAMESPACE__);
        $this->setupTables();
    }

    private function setupTables(): void
    {
        Schema::create('with_scopeable_relation_dummies', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title');
            $table->string('description');
        });

        Schema::create('scopeable_many_to_many_relations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('label');
        });

        Schema::create('scopeable_many_to_many_relation_with_scopeable_relation_dummy', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('scopeable_many_to_many_relation_id');
            $table->bigInteger('with_scopeable_relation_dummy_id');

            $table->foreign('scopeable_many_to_many_relation_id')->references('id')->on('scopeable_many_to_many_relations')->onDelete('cascade');
            $table->foreign('with_scopeable_relation_dummy_id')->references('id')->on('with_scopeable_relation_dummies')->onDelete('cascade');
        });

        Schema::create('users', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('scopeable_many_to_many_relation_user', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('scopeable_many_to_many_relation_id');
            $table->unsignedBigInteger('user_id');

            $table->foreign('scopeable_many_to_many_relation_id')->references('id')->on('scopeable_many_to_many_relations')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }
}
