<?php

namespace Syspons\Sheetable\Exports;

use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Grammars\MySqlGrammar;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use Syspons\Sheetable\Helpers\SheetableLog;
use Syspons\Sheetable\Helpers\SpreadsheetHelper;

/**
 * Implementation for a single sheets export.
 * 
 * @link https://docs.laravel-excel.com/3.1/imports/
 */
class SheetsExport implements FromCollection, WithHeadings, WithEvents, WithTitle, WithStrictNullComparison //, WithColumnFormatting, WithMapping
{
    use Exportable;

    /**
     * The table name.
     */
    private string $tableName;

    public function __construct(
        private Collection $models,
        private Model|string $model,
        private SpreadsheetHelper $helper,
        private bool $isTemplate = false
    ) {
        $this->tableName = $model::newModelInstance()->getTable();
        SheetableLog::log("Start exporting {$this->tableName}".($isTemplate ? ' template' : ''));
    }

    /**
     * The sheet title.
     * 
     * @link https://docs.laravel-excel.com/3.1/exports/multiple-sheets.html#sheet-classes
     */
    public function title(): string
    {
        return $this->tableName.'-Export';
    }

    /**
     * Collection of models that should be exported.
     * 
     * @link https://docs.laravel-excel.com/3.1/exports/collection.html
     */
    public function collection(): Collection
    {
        return $this->models;
    }

    /**
     * Column name listing for exported models.
     * 
     * @link https://docs.laravel-excel.com/3.1/exports/mapping.html#adding-a-heading-row
     */
    public function headings(): array
    {
        try {
            return collect(
                DB::select(
                    (new MySqlGrammar)->compileColumnListing().' order by ordinal_position',
                    [DB::getDatabaseName(), $this->tableName]
                )
            )->pluck('column_name')->toArray();
        } catch (Exception $e) {
            return Schema::getColumnListing($this->tableName);
        }
    }

    /**
     * Processing the worksheet after import.
     * 
     * @link https://docs.laravel-excel.com/3.1/exports/extending.html
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet;
                $workSheet = $sheet->getDelegate();
                SheetableLog::log('AfterSheet started for '.$workSheet->getTitle().'...');

                $dropdownable = $this->model::newModelInstance();

                $this->helper->afterSheetExport($dropdownable, $workSheet, $this->models);

                if ($this->isTemplate) {
                    SheetableLog::log('Clearing values...');
                    $this->helper->clearValues($workSheet);
                    $this->helper->clearStamps($workSheet);
                    SheetableLog::log('Values cleared.');
                }
                SheetableLog::log('AfterSheet ended.');
            },
        ];
    }
}
