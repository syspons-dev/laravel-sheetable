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

class SheetsExport implements FromCollection, WithHeadings, WithEvents, WithTitle, WithStrictNullComparison //, WithColumnFormatting, WithMapping
{
    use Exportable;

    private Model|string $model;
    private Collection $models;
    private string $tableName;
    private SpreadsheetHelper $helper;
    private bool $isTemplate = false;

    public function __construct(
        Collection $models,
        Model|string $model,
        SpreadsheetHelper $helper,
        bool $isTemplate = false
    ) {
        $this->models = $models;
        $this->model = $model;
        $this->tableName = $model::newModelInstance()->getTable();
        $this->helper = $helper;
        $this->isTemplate = $isTemplate;
        SheetableLog::log("Start exporting {$this->tableName}".($isTemplate ? ' template' : ''));
    }

    public function title(): string
    {
        return $this->tableName.'-Export';
    }

    /**
     * collection of models that should be exported.
     */
    public function collection(): Collection
    {
        return $this->models;
    }

    /**
     * column name listing for exported models.
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
