<?php

namespace Syspons\Sheetable\Tests;

use berthott\Scopeable\ScopeableServiceProvider;
use berthott\Translatable\TranslatableServiceProvider;
use Syspons\Sheetable\SheetableServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Maatwebsite\Excel\ExcelServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

abstract class TestCase extends BaseTestCase
{
    public function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app): array
    {
        return [
            SheetableServiceProvider::class,
            ExcelServiceProvider::class,
            ScopeableServiceProvider::class,
            TranslatableServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $this->setupTables();
        Config::set('translatable.namespace', __NAMESPACE__.'\Models');
        Config::set('translatable.languages', ['en' => 'English', 'de' => 'German']);
        Config::set('sheetable.namespace', __NAMESPACE__.'\Models');
        Config::set('scopeable.namespace', __NAMESPACE__.'\Models');
    }

    private function setupTables(): void
    {
        Schema::create('simple_dummies', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title');
            $table->dateTime('date_time');
        });

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

        Schema::create('with_scopeable_relation_dummies', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title');
            $table->string('description');
        });

        Schema::create('many_to_many_relations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('label');
        });

        Schema::create('scopeable_many_to_many_relations', function (Blueprint $table) {
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

        Schema::create('translatable_dummies', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->translatable('title');
        });
    }

    protected function assertSpreadsheetsAreEqual($expectedFilePath, $actualFilePath, $maxSheet = null)
    {
        $reader = new Xlsx();
        $reader->setReadDataOnly(true);

        $spreadsheetExpected = $reader->load($expectedFilePath);
        $spreadsheetActual = $reader->load($actualFilePath);
        $sheetCount = min(max($spreadsheetExpected->getSheetCount(), $spreadsheetActual->getSheetCount()), $maxSheet);
        
        foreach (range(0, --$sheetCount) as $sheet) {
            $rowCount = max(
                $spreadsheetExpected->getSheet($sheet)->getCellCollection()->getHighestRow(),
                $spreadsheetActual->getSheet($sheet)->getCellCollection()->getHighestRow()
            );
            $colCount = max(
                $spreadsheetExpected->getSheet($sheet)->getCellCollection()->getHighestColumn(),
                $spreadsheetActual->getSheet($sheet)->getCellCollection()->getHighestColumn()
            );
            foreach (range(1, $rowCount) as $row) {
                for ($column = 'A'; $column <= $colCount; $column++) {
                    $cell = $column . $row;
                    $expected = $spreadsheetExpected->getSheet($sheet)->getCell($cell)->getValue();
                    $actual = $spreadsheetActual->getSheet($sheet)->getCell($cell)->getValue();
                    $this->assertEquals($expected, $actual, "Mismatch in sheet {$sheet}, cell {$cell}");
                }
            }
        }
    }
    
    protected function assertExpectedSpreadsheetResponse(TestResponse $response, string $expectedPath, bool $delete = true, $maxSheet = null)
    {
        $storagePath = __FUNCTION__;
        $expectedName = basename($expectedPath);
        
        // store response in storage
        $response->getFile()->move(Storage::path($storagePath), $expectedName.'_actual');

        // store expected in storage
        Storage::putFileAs($storagePath, new UploadedFile(
            $expectedPath,
            $expectedName,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        ), $expectedName.'_expect');

        $actualPath = $storagePath.'/'.$expectedName.'_actual';
        $expectedPath = $storagePath.'/'.$expectedName.'_expect';

        $this->assertSpreadsheetsAreEqual(
            Storage::path($actualPath),
            Storage::path($expectedPath),
        );

        if ($delete) {
            Storage::delete($actualPath);
            Storage::delete($expectedPath);
        }
    }
}
