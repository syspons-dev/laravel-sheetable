<?php

namespace Syspons\Sheetable\Tests\Feature\JoinTranslatableTest;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Syspons\Sheetable\Tests\TestCase;

abstract class JoinTranslatableTestCase extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
    }

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);
        Config::set('translatable.languages', ['en' => 'English', 'de' => 'German']);
        Config::set('sheetable.namespace', __NAMESPACE__);
        $this->setupTables();
    }

    private function setupTables(): void
    {
        Schema::create('one_to_many_translatable_dummies', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->translatable('title');
        });
        
        Schema::create('many_to_one_translatable_dummies', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->translatable('title');
            $table->foreignId('joinable_dummy_id')->constrained();
        });
        
        Schema::create('many_to_many_translatable_dummies', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->translatable('title');
        });
        
        Schema::create('joinable_dummy_many_to_many_translatable_dummy', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('joinable_dummy_id')->constrained();
            $table->foreignId('many_to_many_translatable_dummy_id')->constrained();
        });

        Schema::create('joinable_dummies', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title');
            $table->string('description');

            $table->foreignId('one_to_many_translatable_dummy_id')->constrained();
        });
    }
}
