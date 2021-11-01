<?php

namespace Syspons\Sheetable\Services;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Sheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Syspons\Sheetable\Exports\DropdownSettings;
use Syspons\Sheetable\Models\Contracts\Dropdownable;

class SpreadsheetHelper
{
    public function getMetaSheetName(): string
    {
        return 'metadata';
    }

    /**
     * Adds validation/dropdown-fields for all recipes defined in getDropdownFields.
     *
     * @param Dropdownable $dropdownable dropdownable model
     */
    public function exportDropdownFields(Dropdownable $dropdownable, Worksheet $sheet)
    {
        $dropdownFields = $dropdownable::getDropdownFields();
        foreach ($dropdownFields as $dropdownSettings) {
            if ($dropdownSettings->isEmbeddedValues()) {
                $this->addForeignKeyDropdownColumnEmbedded($sheet, $dropdownSettings);
                continue;
            }
            $this->addForeignKeyDropdownColumn($sheet, $dropdownSettings);
        }
    }

    /**
     * get the Sheet containing meta data info like field validation references.
     *
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    public function getMetadataSheet(Spreadsheet $spreadsheet): Worksheet
    {
        $metaSheetName = $this->getMetaSheetName();

        $metaSheet = $spreadsheet->getSheetByName($metaSheetName);

        if (null === $metaSheet) {
            $metaSheet = new Worksheet($spreadsheet, $metaSheetName);
            $spreadsheet->addSheet($metaSheet, 1);
        }

        return $metaSheet;
    }

    /**
     * returns column e.g. "B".
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

    public function addForeignKeyDropdownColumn(Worksheet $sheet, DropdownSettings $dropdownSettings): void
    {
        $column = $this->getColumnByHeading($sheet, $dropdownSettings->getField());
        $highestRow = $sheet->getHighestDataRow($column);
        $metaDataSheet = $this->getMetaDataSheet($sheet->getParent());

        $foreignModelShort = $this->getModelShortname($dropdownSettings->getForeignModel());

        $this->createRefColumnsForField($sheet->getParent(), $dropdownSettings->getForeignModel(), $dropdownSettings->getForeignTitleColumn());
        $refValCol = $this->getColumnByHeading($metaDataSheet,
            $foreignModelShort.'.'.$dropdownSettings->getForeignTitleColumn()
        );

        $highestValRow = $metaDataSheet->getHighestDataRow($refValCol);
        $selectOptions = $this->getMetaSheetName().'!$'.$refValCol.'$2:$'.$refValCol.'$'.$highestValRow;

        for ($i = 2; $i <= $highestRow; ++$i) {
            $this->addForeignKeyDropdownField($sheet, $column.$i, $dropdownSettings->getForeignModel(), $dropdownSettings->getForeignTitleColumn(), $selectOptions);
        }
    }

    /**
     * Creates reference (id + describing value) columns within metadata sheet.
     *
     * @param Model|string $foreignModel        The modle containing the foreign (key) id and the describing value
     * @param string       $foreignDescDbColumn DB/Model field containing the describing value
     *
     * @throws Exception
     */
    public function createRefColumnsForField(Spreadsheet $spreadsheet, Model|string $foreignModel, string $foreignDescDbColumn)
    {
        $metaDataSheet = $this->getMetaDataSheet($spreadsheet);
        $metaIdColumnName = $this->getFirstEmptyColumnName($metaDataSheet);
        $metaValueColumnName = $this->getNextCol($metaIdColumnName);
        $metaRowNumber = 1;

        /** @var Collection $descriptions */
        $descriptions = $foreignModel::select('id', $foreignDescDbColumn)->get();

        $foreignModelShort = $this->getModelShortname($foreignModel);

        // Headings
        $metaDataSheet->setCellValue($metaIdColumnName.$metaRowNumber, $foreignModelShort.'.id');
        $metaDataSheet->setCellValue($metaValueColumnName.$metaRowNumber, $foreignModelShort.'.'.$foreignDescDbColumn);

        foreach ($descriptions as $description) {
            ++$metaRowNumber;
            $id = $description->id;
            $value = $description->toArray()[$foreignDescDbColumn];
            $metaDataSheet->setCellValue($metaIdColumnName.$metaRowNumber, $id);
            $metaDataSheet->setCellValue($metaValueColumnName.$metaRowNumber, $value);
        }
    }

    private function getModelShortname(Model|string $model): string
    {
        $path = explode('\\', $model);

        return array_pop($path);
    }

    public function getFirstEmptyColumnName(Worksheet $sheet): string
    {
        $highestCol = $sheet->getHighestDataColumn();
        $highestColIndex = Coordinate::columnIndexFromString($highestCol);

        return Coordinate::stringFromColumnIndex($highestColIndex + 1);
    }

    private function getNextCol(string $colName): string
    {
        $colIndex = Coordinate::columnIndexFromString($colName);

        return Coordinate::stringFromColumnIndex($colIndex + 1);
    }

    private function addForeignKeyDropdownColumnEmbedded(Sheet|Worksheet $sheet, DropdownSettings $dropdownSettings): void
    {
        $column = $this->getColumnByHeading($sheet, $dropdownSettings->getField());
        $highestRow = $sheet->getHighestRow();
        $selectOptions = $this->getEmbeddedValidationFormulaForForeignModel(
            $dropdownSettings->getForeignModel(), $dropdownSettings->getForeignTitleColumn()
        );

        for ($i = 2; $i <= $highestRow; ++$i) {
            $this->addForeignKeyDropdownField(
                $sheet,
                $column.$i,
                $dropdownSettings->getForeignModel(),
                $dropdownSettings->getForeignTitleColumn(),
                $selectOptions
            );
        }
    }

    private function addForeignKeyDropdownField(
        Sheet|Worksheet $sheet,
        string $spreadSheetField,
        Model|string $foreignModel,
        string $foreignTitleColumn,
        string $validationFormula
    ): void {
        $cellId = $sheet->getCell($spreadSheetField)->getValue();
        $cellValue = $this->getDescValueForId($foreignModel, $cellId, $foreignTitleColumn);
        $this->addDropdownField($sheet, $spreadSheetField, $cellValue, $validationFormula);
    }

    private function getDescValueForId(Model|string $model, $id, $descValueColumnName)
    {
        $currentModel = $model::find($id);

        return $currentModel->toArray()[$descValueColumnName];
    }

    private function getEmbeddedValidationFormulaForForeignModel(Model|string $foreignModel, string $foreignDescColumn): string
    {
        /** @var Collection $descriptions */
        $descriptions = $foreignModel::select($foreignDescColumn)->get();
        $selectOptions = implode(',', $descriptions->pluck($foreignDescColumn)->toArray());
        if (strlen($selectOptions) > 255) {
            $selectOptions = substr($selectOptions, 0, 255);
        }

        return '"'.$selectOptions.'"';
    }

    /**
     * Handles validation/dropdown-fields getDropdownFields.
     *
     * @param Dropdownable $dropdownable dropdownable model
     */
    public function importDropdownFields(Dropdownable $dropdownable, Worksheet $sheet)
    {
        $dropdownFields = $dropdownable::getDropdownFields();
        foreach ($dropdownFields as $dropdownSettings) {
            $this->resolveIdForDropdownField($sheet, $dropdownSettings);
        }
    }

    /**
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    public function resolveIdForDropdownField(Worksheet $sheet, DropdownSettings $dropdownSettings): void
    {
        $column = $this->getColumnByHeading($sheet, $dropdownSettings->getField());
        $highestRow = $sheet->getHighestDataRow($column);

        $metaDataSheet = $this->getMetaDataSheet($sheet->getParent());
        $foreignModelShort = $this->getModelShortname($dropdownSettings->getForeignModel());

        $refIdCol = $this->getColumnByHeading($metaDataSheet, $foreignModelShort.'.id');
        $refValCol = $this->getColumnByHeading($metaDataSheet,
            $foreignModelShort.'.'.$dropdownSettings->getForeignTitleColumn()
        );

        $highestRefValRow = $metaDataSheet->getHighestDataRow($refValCol);

        $arrValuesToRowNr = $this->getValuesIndexedArray($metaDataSheet, $refValCol, 2, $highestRefValRow);

        for ($i = 2; $i <= $highestRow; ++$i) {
            $cell = $sheet->getCell($column.$i);
            $rawValue = $cell->getValue();
            $rowNr = $arrValuesToRowNr[$rawValue];

            $resolvedId = $metaDataSheet->getCell($refIdCol.$rowNr)->getValue();
            $cell->setValue($resolvedId);
        }
    }

    /**
     * @param string $column   e.g. A
     * @param int    $startRow e.g. 2
     * @param int    $endRow   e.g. 99
     *
     * @return array eg [ 'SomeValue' => 2]
     *
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    public function getValuesIndexedArray(Worksheet $worksheet, string $column, int $startRow, int $endRow): array
    {
        $arr = [];
        for ($i = $startRow; $i <= $endRow; ++$i) {
            $val = $worksheet->getCell($column.$i)->getValue();
            $arr[$val] = $i;
        }

        return $arr;
    }

    /**
     * @param string $spreadSheetField  e.g. 'C2'
     * @param string $cellValue         the describing value to be displayed, not the corresponding (db) id
     * @param string $validationFormula e.g. '"Item 1, Item 2, Item 3"' or 'metadata!$C$2:$C$240'
     *
     * @throws Exception
     */
    public function addDropdownField(Worksheet $worksheet, string $spreadSheetField, string $cellValue, string $validationFormula): void
    {
        $worksheet->setCellValue($spreadSheetField, $cellValue);

        $objValidation = $worksheet->getCell($spreadSheetField)->getDataValidation();
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
        $objValidation->setFormula1($validationFormula);
    }
}
