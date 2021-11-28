<?php

/** @noinspection PhpMultipleClassDeclarationsInspection */

namespace Syspons\Sheetable\Imports;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Events\BeforeSheet;
use Syspons\Sheetable\Helpers\SpreadsheetHelper;
use Syspons\Sheetable\Models\Contracts\Sheetable;

class SheetImport implements ToCollection, WithHeadingRow, WithValidation, WithEvents, SkipsEmptyRows
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

    public function collection(Collection $collection)
    {
        $this->helper->importCollection($collection, $this->modelClass);
    }

    public function registerEvents(): array
    {
        return [
            BeforeSheet::class => function (BeforeSheet $event) {
                $sheet = $event->sheet;
                $this->helper->beforeSheetImport($this->modelClass, $sheet->getDelegate());
            },
        ];
    }

    public function rules(): array
    {
        /** @var Sheetable $sheetable */
        $sheetable = $this->modelClass::newModelInstance();

        return $sheetable::importRules();
    }
}
