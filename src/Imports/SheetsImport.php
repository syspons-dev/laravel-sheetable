<?php

/** @noinspection PhpMultipleClassDeclarationsInspection */

namespace Syspons\Sheetable\Imports;

use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Syspons\Sheetable\Helpers\SpreadsheetHelper;

class SheetsImport implements WithMultipleSheets
{
    private string|Model $modelClass;
    private SpreadsheetHelper $helper;

    public function __construct(
        string|Model $modelClass,
        SpreadsheetHelper $helper
    ) {
        $this->modelClass = $modelClass;
        $this->helper = $helper;
    }

    public function sheets(): array
    {
        return [
            0 => new SheetImport($this->modelClass, $this->helper),
        ];
    }
}
