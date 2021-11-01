<?php

namespace Syspons\Sheetable\Exports;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use Schema;
use Syspons\Sheetable\Models\Contracts\Dropdownable;
use Syspons\Sheetable\Services\SpreadsheetHelper;

class SheetsExport implements FromCollection, WithHeadings, WithEvents, WithTitle
{
    use Exportable;

    private Model|string $model;
    private Collection $models;
    private string $tableName;
    private SpreadsheetHelper $helper;

    public function __construct(
        Collection $models,
        Model|string $model,
        SpreadsheetHelper $helper
    ) {
        $this->models = $models;
        $this->model = $model;
        $this->tableName = $model::newModelInstance()->getTable();
        $this->helper = $helper;
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
        return Schema::getColumnListing($this->tableName);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet;
                $workSheet = $sheet->getDelegate();

                /** @var Dropdownable $dropdownable */
                $dropdownable = $this->model::newModelInstance();

                if (method_exists($this->model, 'getDropdownFields')) {
                    $this->helper->exportDropdownFields($dropdownable, $workSheet);
                }
            },
        ];
    }
}
