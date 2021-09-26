<?php

namespace Syspons\Sheetable\Exports;

use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Schema;

class SheetsExport implements FromCollection, WithHeadings
{
    use Exportable;

    private Collection $models;
    private string $tableName;

    public function __construct(
        Collection $models,
        string $tableName
    ) {
        $this->models = $models;
        $this->tableName = $tableName;
    }

    /**
     * collection of models that should be exported.
     */
    public function collection(): Collection
    {
        return $this->models;
    }

    public function headings(): array
    {
        return Schema::getColumnListing($this->tableName);
    }
}
