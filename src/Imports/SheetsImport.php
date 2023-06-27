<?php

namespace Syspons\Sheetable\Imports;

use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Syspons\Sheetable\Helpers\SpreadsheetHelper;

/**
 * WithMultipleSheets implementation for a multiple sheets import.
 * 
 * @link https://docs.laravel-excel.com/3.1/imports/multiple-sheets.html
 */
class SheetsImport implements WithMultipleSheets
{
    public function __construct(
        private string|Model $modelClass,
        private SpreadsheetHelper $helper
    ) {}

    /**
     * The sheets to export.
     * 
     * A single sheet with a {@see \Syspons\Sheetable\Imports\SheetImport}
     */
    public function sheets(): array
    {
        return [
            0 => new SheetImport($this->modelClass, $this->helper),
        ];
    }
}
