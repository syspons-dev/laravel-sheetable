<?php

namespace Syspons\Sheetable\Tests;

use berthott\Scopeable\ScopeableServiceProvider;
use berthott\Translatable\TranslatableServiceProvider;
use Syspons\Sheetable\SheetableServiceProvider;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Maatwebsite\Excel\ExcelServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

abstract class TestCase extends BaseTestCase
{

    protected function getPackageProviders($app): array
    {
        return [
            SheetableServiceProvider::class,
            ExcelServiceProvider::class,
            ScopeableServiceProvider::class,
            TranslatableServiceProvider::class,
        ];
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
