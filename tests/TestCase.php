<?php

namespace Syspons\Sheetable\Tests;

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
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $this->setupTables();
        Config::set('sheetable.namespace', __NAMESPACE__);
    }

    private function setupTables(): void
    {
        Schema::create('simple_dummies', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title');
        });

        Schema::create('with_relation_dummies', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title');
            $table->string('description');
            $table->string('one_to_many_relation_id');
            $table->timestamps();
        });

        Schema::create('one_to_many_relations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('label');
            $table->timestamps();
        });

        Schema::create('many_to_many_relations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('label');
            $table->timestamps();
        });

        Schema::create('many_to_many_relation_with_relation_dummy', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('many_to_many_relation_id');
            $table->bigInteger('with_relation_dummy_id');
            $table->timestamps();
        });
    }

    protected function assertSpreadsheetsAreEqual($expectedFilePath, $actualFilePath)
    {
        $reader = new Xlsx();
        $reader->setReadDataOnly(true);

        $spreadsheetExpected = $reader->load($expectedFilePath);
        $spreadsheetActual = $reader->load($actualFilePath);
        $sheetCount = max($spreadsheetExpected->getSheetCount(), $spreadsheetActual->getSheetCount());
        
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
    
    protected function assertExpectedSpreadsheetResponse(TestResponse $response, string $expectedPath, bool $delete = true)
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
