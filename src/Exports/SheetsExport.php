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
use Maatwebsite\Excel\Sheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Schema;
use Syspons\Sheetable\Models\Contracts\Dropdownable;

class ForeignKeyDropdownSettings
{
    private string $foreignModel;
    private string $foreignTitleColumn;

    public function __construct(
        string $foreignModel,
        string $foreignTitleColumn
    )
    {
        $this->foreignModel = $foreignModel;
        $this->foreignTitleColumn = $foreignTitleColumn;
    }

    public function getForeignModel(): string
    {
        return $this->foreignModel;
    }

    public function getForeignTitleColumn(): string
    {
        return $this->foreignTitleColumn;
    }
}

class SheetsExport implements FromCollection, WithHeadings, WithEvents, WithTitle
{
    use Exportable;

    private Model|string $model;
    private Collection $models;
    private string $tableName;

    public function __construct(
        Collection $models,
        Model|string $model
    )
    {
        $this->models = $models;
        $this->model = $model;
        $this->tableName = $model::newModelInstance()->getTable();
    }

    public function title(): string
    {
        return $this->tableName . '-Export';
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

                /** @var Sheet|Worksheet $sheet */
                $sheet = $event->sheet;

                /** @var Dropdownable $dropdownable */
                $dropdownable = $this->model::newModelInstance();
                $dropdownFields = $dropdownable::getDropdownFields();

                foreach ($dropdownFields as $columnKey => $dropdownSettings) {
                    $this->addForeignKeyDropdownColumn(
                        $sheet, $columnKey, $dropdownSettings['foreignModel'], $dropdownSettings['foreignTitleColumn']
                    );
                }

            }
        ];
    }

    /**
     * returns column e.g. "B"
     */
    public function getColumnByHeading(Sheet|Worksheet $sheet, string $heading): ?string
    {
        $row = $sheet->getRowIterator(1)->current();

        $cellIterator = $row->getCellIterator();
        $cellIterator->setIterateOnlyExistingCells(false);

        foreach ($cellIterator as $cell) {
            if ($heading === $cell->getValue()) {
                return $cell->getColumn();
            }
        }
        return null;
    }

    public function addForeignKeyDropdownColumn(Sheet|Worksheet $sheet, string $columnHeading, Model|string $foreignModel, string $foreignTitleColumn): void
    {
        $column = $this->getColumnByHeading($sheet, $columnHeading);
        $highestRow = $sheet->getHighestRow();

        for ($i = 2; $i <= $highestRow; ++$i) {
            $this->addForeignKeyDropdownField($sheet, $column . $i, $foreignModel, $foreignTitleColumn);
        }
    }

    public function addForeignKeyDropdownField(Sheet|Worksheet $sheet, string $spreadSheetField, Model|string $foreignModel, string $foreignDescColumn): void
    {

        /** @var Collection $descriptions */
        $descriptions = $foreignModel::select($foreignDescColumn)->get();
        $selectOptions = implode(', ', $descriptions->pluck($foreignDescColumn)->toArray());
        $cellId = $sheet->getCell($spreadSheetField)->getValue();

        $cellDescr = $foreignModel::find($cellId)->code;
        $sheet->setCellValue($spreadSheetField, $cellDescr);

        $objValidation = $sheet->getCell($spreadSheetField)->getDataValidation();
        $objValidation->setType(DataValidation::TYPE_LIST);
        $objValidation->setErrorStyle(DataValidation::STYLE_INFORMATION);
        $objValidation->setAllowBlank(false);
        $objValidation->setShowInputMessage(true);
        $objValidation->setShowErrorMessage(true);
        $objValidation->setShowDropDown(true);
        $objValidation->setErrorTitle('Input error');
        $objValidation->setError('Value is not in list.');
        $objValidation->setPromptTitle('Pick from list');
        $objValidation->setPrompt('Please pick a value from the drop-down list.');
        $objValidation->setFormula1($selectOptions);

    }


}
