<?php

namespace Syspons\Sheetable\Exports;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use Syspons\Sheetable\Helpers\SheetableLog;
use Syspons\Sheetable\Helpers\SpreadsheetHelper;
use Facades\Syspons\Sheetable\Helpers\SpreadsheetJoins;
use Facades\Syspons\Sheetable\Helpers\SpreadsheetUtils;

/**
 * Implementation for a single sheets export.
 * 
 * @link https://docs.laravel-excel.com/3.1/imports/
 */
class SheetsExport implements FromCollection, WithHeadings, WithEvents, WithTitle, WithStrictNullComparison, WithMapping
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
        private bool $isTemplate = false,
        private array $select = [],
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
     * If {@link selected} is set, only the selected attributes will be included.
     * 
     * @link https://docs.laravel-excel.com/3.1/exports/collection.html
     */
    public function collection(): Collection
    {
        return empty($this->select) 
            ? $this->models
            : $this->models->map(fn ($e) => $e->setVisible($this->select));
    }

    /**
     * Collection of models that should be exported.
     * 
     * If {@link selected} is set, only the selected attributes will be included.
     * 
     * @link https://docs.laravel-excel.com/3.1/exports/collection.html
     */
    public function map($entity): array
    {
        return SpreadsheetJoins::getMapping($entity, $this->headings());
    }

    /**
     * Column name listing for exported models.
     * 
     * If {@link selected} is set, only the selected attributes will be included.
     * 
     * @link https://docs.laravel-excel.com/3.1/exports/mapping.html#adding-a-heading-row
     */
    public function headings(): array
    {
        $columns = SpreadsheetUtils::getOrdinalColumnNames($this->tableName);
        $columns = empty($this->select)
            ? $columns
            : array_intersect($columns, $this->select);
        
        $columns = method_exists($this->model, 'getJoins')
            ? SpreadsheetJoins::getHeadings($this->model, $columns)
            : $columns;

        // handle reordering
        if (method_exists($this->model, 'exportMapping')) {
            SheetableLog::log('Reordering columns...');
            $mapping = Arr::flatten(Arr::map($this->model::exportMapping(), fn($value) => is_array($value) && array_key_exists('select', $value) ? $value['select'] : $value));
            $columns = array_intersect($mapping, $columns);
            SheetableLog::log('Columns reordered.');
        }

        return $columns;
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
