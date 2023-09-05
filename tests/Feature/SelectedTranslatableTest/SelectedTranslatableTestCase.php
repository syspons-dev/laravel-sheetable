<?php

namespace Syspons\Sheetable\Tests\Feature\SelectedTranslatableTest;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Syspons\Sheetable\Tests\TestCase;

abstract class SelectedTranslatableTestCase extends TestCase
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
        Schema::create('translatable_dummies', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->translatable('title');
        });
    }
}
