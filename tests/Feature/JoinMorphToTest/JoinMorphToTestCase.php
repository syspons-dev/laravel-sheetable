<?php

namespace Syspons\Sheetable\Tests\Feature\JoinMorphToTest;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Syspons\Sheetable\Tests\TestCase;

abstract class JoinMorphToTestCase extends TestCase
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
            $table->morphs('joinable_relatable');
        });

        Schema::create('joinable_dummies', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title');
            $table->string('description');
        });
    }
}
