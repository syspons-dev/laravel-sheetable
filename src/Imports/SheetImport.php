<?php

/** @noinspection PhpMultipleClassDeclarationsInspection */

namespace Syspons\Sheetable\Imports;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Events\BeforeSheet;
use Syspons\Sheetable\Helpers\SheetableLog;
use Syspons\Sheetable\Helpers\SpreadsheetHelper;
use Syspons\Sheetable\Models\Contracts\Sheetable;

class SheetImport implements ToCollection, WithHeadingRow, WithValidation, WithEvents, SkipsEmptyRows, WithCalculatedFormulas
{
    private string|Model $modelClass;
    private SpreadsheetHelper $helper;

    public function __construct(
        string|Model $modelClass,
        SpreadsheetHelper $helper
    ) {
        SheetableLog::log("Start importing $modelClass");
        $this->modelClass = $modelClass;
        $this->helper = $helper;
    }

    public function collection(Collection $collection)
    {
        SheetableLog::log('Parsing ended.');
        SheetableLog::log('Importing...');
        $this->helper->importCollection($collection, $this->modelClass);
        SheetableLog::log('Imported '.$collection->count().' entries: '.$collection->pluck($this->modelClass::newModelInstance()->getKeyName())->join(', '));
    }

    public function registerEvents(): array
    {
        return [
            BeforeSheet::class => function (BeforeSheet $event) {
                $worksheet = $event->sheet->getDelegate();
                SheetableLog::log('BeforeSheet started for '.$worksheet->getTitle().'...');
                $this->helper->beforeSheetImport($this->modelClass, $worksheet);
                SheetableLog::log('BeforeSheet ended.');
                SheetableLog::log('Parsing started...');
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
