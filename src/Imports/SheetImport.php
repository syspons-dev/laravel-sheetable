<?php

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

/**
 * Implementation for a single sheets import.
 * 
 * @link https://docs.laravel-excel.com/3.1/imports/
 */
class SheetImport implements ToCollection, WithHeadingRow, WithValidation, WithEvents, SkipsEmptyRows, WithCalculatedFormulas
{
    public function __construct(
        private string|Model $modelClass,
        private SpreadsheetHelper $helper
    ) {
        SheetableLog::log("Start importing $modelClass");
    }

    /**
     * Import the collection.
     * 
     * @link https://docs.laravel-excel.com/3.1/imports/collection.html
     */
    public function collection(Collection $collection)
    {
        SheetableLog::log('Parsing ended.');
        SheetableLog::log('Importing...');
        $this->helper->importCollection($collection, $this->modelClass);
        SheetableLog::log('Imported '.$collection->count().' entries: '.$collection->pluck($this->modelClass::newModelInstance()->getKeyName())->join(', '));
    }

    /**
     * Process the worksheet before importing.
     * 
     * @link https://docs.laravel-excel.com/3.1/imports/collection.html
     */
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

    /**
     * The import validation rules.
     * 
     * @link https://docs.laravel-excel.com/3.1/imports/validation.html
     */
    public function rules(): array
    {
        /** @var Sheetable $sheetable */
        $sheetable = $this->modelClass::newModelInstance();

        return $sheetable::importRules();
    }
}
