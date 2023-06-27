<?php

/** @noinspection PhpMultipleClassDeclarationsInspection */

namespace Syspons\Sheetable\Imports;

use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Syspons\Sheetable\Helpers\SpreadsheetHelper;

class SheetsImport implements WithMultipleSheets
{
    public function __construct(
        private string|Model $modelClass,
        private SpreadsheetHelper $helper
    ) {}

    public function sheets(): array
    {
        return [
            0 => new SheetImport($this->modelClass, $this->helper),
        ];
    }
}
