<?php

namespace Syspons\Sheetable\Services;

use Exception;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Sheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Exception as PhpSpreadsheetException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Syspons\Sheetable\Exports\DropdownConfig;
use Syspons\Sheetable\Models\Contracts\Dropdownable;

class SpreadsheetHelper
{
    private string $metaSheetName = 'metadata';
    private int $extraDropdownFieldsCount = 100;

    /**
     * Adds validation/dropdown-fields for all recipes defined in getDropdownFields.
     *
     * @param Dropdownable $dropdownable dropdownable model
     *
     * @throws PhpSpreadsheetException
     */
    public function exportDropdownFields(Dropdownable $dropdownable, Worksheet $worksheet)
    {
        $dropdownFields = $dropdownable::getDropdownFields();
        foreach ($dropdownFields as $dropdownSettings) {
            if ($dropdownSettings->isEmbedded()) {
                $this->addForeignKeyDropdownColumnEmbedded($worksheet, $dropdownSettings);
                continue;
            }
            $this->addForeignKeyDropdownColumn($worksheet, $dropdownSettings);
        }
    }

    /**
     * get the Sheet containing meta data info like field validation references.
     *
     * @throws PhpSpreadsheetException
     */
    public function getMetadataSheet(Spreadsheet $spreadsheet): Worksheet
    {
        $metaSheet = $spreadsheet->getSheetByName($this->metaSheetName);

        if (null === $metaSheet) {
            $metaSheet = new Worksheet($spreadsheet, $this->metaSheetName);
            $spreadsheet->addSheet($metaSheet, 1);
        }

//        TODO sheet protection
        $metaSheet->getProtection()->setSheet(true);
        return $metaSheet;
    }

    /**
     * returns column e.g. "B". or null.
     */
    public function getColumnByHeading(Sheet|Worksheet $sheet, string $heading): ?string
    {
        $row = $sheet->getRowIterator()->current();

        $cellIterator = $row->getCellIterator();
        $cellIterator->setIterateOnlyExistingCells(false);

        foreach ($cellIterator as $cell) {
            if ($heading === $cell->getValue()) {
                return $cell->getColumn();
            }
        }

        return null;
    }

    /**
     * adds / replaces the IDs of all fields - containing values - in a given column
     * with the corresponding describing text field and adds a dropdown to all of these fields
     * containing the valid values, referencing a range in the metadata-sheet
     * Uses the DropdownConfig-Object to determine the Field and the recipes.
     *
     * @throws PhpSpreadsheetException
     * @throws Exception
     */
    public function addForeignKeyDropdownColumn(Worksheet $sheet, DropdownConfig $dropdownConfig): void
    {
        $column = $this->getColumnByHeading($sheet, $dropdownConfig->getField());
        $highestRow = $sheet->getHighestDataRow($column);
        $metaDataSheet = $this->getMetaDataSheet($sheet->getParent());

        $foreignModelShort = $this->getModelShortname($dropdownConfig->getFkModel());

        $this->createRefColumnsForField($sheet->getParent(), $dropdownConfig);
        $refValCol = $this->getColumnByHeading($metaDataSheet,
            $foreignModelShort.'.'.$dropdownConfig->getFkTextCol()
        );

        $highestValRow = $metaDataSheet->getHighestDataRow($refValCol);
        $selectOptions = $this->getMetaSheetName().'!$'.$refValCol.'$2:$'.$refValCol.'$'.$highestValRow;

        for ($i = 2; $i <= $highestRow + $this->extraDropdownFieldsCount; ++$i) {
            $this->addForeignKeyDropdownField(
                $sheet,
                $i,
                $dropdownConfig,
                $selectOptions
            );
        }
    }

    /**
     * Creates reference (id + describing value) columns within metadata sheet.
     *
     * @throws PhpSpreadsheetException
     */
    public function createRefColumnsForField(Spreadsheet $spreadsheet, DropdownConfig $config)
    {
        $metaDataSheet = $this->getMetaDataSheet($spreadsheet);
        $metaIdCol = $this->getFirstEmptyColumnName($metaDataSheet);
        $metaValueCol = $this->getNextCol($metaIdCol);
        $row = 1;

        $descriptions = $config->getFkModel()::select([$config->getFkIdCol(), $config->getFkTextCol()])->get();
        $foreignModelShort = $this->getModelShortname($config->getFkModel());

        // Set Headings
        $metaDataSheet->setCellValue($metaIdCol.$row, $foreignModelShort.'.id');
        $metaDataSheet->setCellValue($metaValueCol.$row,
            $foreignModelShort.'.'.$config->getFkTextCol()
        );

        foreach ($descriptions as $description) {
            ++$row;
            $id = $description->id;
            $value = $description->toArray()[$config->getFkTextCol()];
            $metaDataSheet->setCellValue($metaIdCol.$row, $id);
            $metaDataSheet->setCellValue($metaValueCol.$row, $value);
        }
    }

    /**
     * shortens qualified class name like 'App\Models\Dummy' to shortname like 'Dummy'.
     *
     * @param string $model e.g. App\Models\Dummy
     *
     * @return string e.g. Dummy
     */
    private function getModelShortname(string $model): string
    {
        $path = explode('\\', $model);

        return array_pop($path);
    }

    /**
     * Finds the first column not containing any values in the give worksheet.
     *
     * @return string e.g. 'B'
     *
     * @throws PhpSpreadsheetException
     */
    public function getFirstEmptyColumnName(Worksheet $worksheet): string
    {
        $highestCol = $worksheet->getHighestDataColumn();
        $highestColIndex = Coordinate::columnIndexFromString($highestCol);

        return Coordinate::stringFromColumnIndex($highestColIndex + 1);
    }

    /**
     * Get the next Column.
     *
     * @param string $colName e.g. 'B'
     *
     * @return string e.g. 'C'
     *
     * @throws PhpSpreadsheetException
     */
    private function getNextCol(string $colName): string
    {
        $colIndex = Coordinate::columnIndexFromString($colName);

        return Coordinate::stringFromColumnIndex($colIndex + 1);
    }

    /**
     * Adds / replaces the IDs of all fields - containing values - in a given column
     * with the corresponding describing text field and adds a dropdown to these fields
     * containing the valid values, embedded formula values.
     * WARNING: The sum of all chars, including commas must not exceed 255! Gets cut otherwise.
     * Uses the DropdownConfig-Object to determine the Field and the recipes.
     *
     * @param Sheet|Worksheet $sheet
     * @param DropdownConfig  $dropdownConfig Field-DropdownConfig
     *
     * @throws PhpSpreadsheetException
     */
    private function addForeignKeyDropdownColumnEmbedded(Sheet|Worksheet $sheet, DropdownConfig $dropdownConfig): void
    {
        $highestRow = $sheet->getHighestRow();
        $selectOptions = $this->getEmbeddedValidationFormulaForForeignModel($dropdownConfig);

        for ($i = 2; $i <= $highestRow; ++$i) {
            $this->addForeignKeyDropdownField(
                $sheet,
                $i,
                $dropdownConfig,
                $selectOptions
            );
        }
    }

    /**
     * @param Sheet|Worksheet $worksheet
     * @param DropdownConfig  $config            Field-DropdownConfig
     * @param string          $validationFormula e.g. 'foo, bar' or 'metadata!B1:B3'
     *
     * @throws PhpSpreadsheetException
     * @throws Exception
     */
    private function addForeignKeyDropdownField(
        Sheet|Worksheet $worksheet,
        int $rowNr,
        DropdownConfig $config,
        string $validationFormula
    ): void {
        $colCoord = $this->getColumnByHeading($worksheet, $config->getField());
        $cellCoord = $colCoord.$rowNr;
        $fkId = $worksheet->getCell($cellCoord)->getValue();
        $fkText = $this->getDescValueForId($fkId, $config);
        $this->addDropdownField($worksheet, $cellCoord, $fkText, $validationFormula);
    }

    /**
     * Get the reference descriptive string value for given id.
     *
     * @param int $id e.g. 1
     *
     * @return string e.g. 'John'
     */
    private function getDescValueForId(?int $id, DropdownConfig $config): ?string
    {
        if (null == $id) {
            return $id;
        }
        $currentModel = $config->getFkModel()::find($id);

        return $currentModel->toArray()[$config->getFkTextCol()];
    }

    /**
     * @param DropdownConfig $config Field-DropdownConfig
     *
     * @return string Formula
     */
    private function getEmbeddedValidationFormulaForForeignModel(DropdownConfig $config): string
    {
        /** @var Collection $descriptions */
        $descriptions = $config->getFkModel()::select($config->getFkTextCol())->get();
        $selectOptions = implode(',', $descriptions->pluck($config->getFkTextCol())->toArray());
        if (strlen($selectOptions) > 255) {
            $selectOptions = substr($selectOptions, 0, 255);
        }

        return '"'.$selectOptions.'"';
    }

    /**
     * Handles validation/dropdown-fields getDropdownFields.
     *
     * @param Dropdownable $dropdownable dropdownable model
     *
     * @throws PhpSpreadsheetException
     */
    public function importDropdownFields(Dropdownable $dropdownable, Worksheet $sheet)
    {
        $dropdownFields = $dropdownable::getDropdownFields();
        foreach ($dropdownFields as $dropdownSettings) {
            $this->resolveIdsForDropdownColumn($sheet, $dropdownSettings);
        }
    }

    /**
     * Replaces the descriptive text(dropdown) fields with the corresponding IDs.
     *
     * @param DropdownConfig $dropdownSettings Field-DropdownConfig
     *
     * @throws PhpSpreadsheetException
     */
    public function resolveIdsForDropdownColumn(Worksheet $worksheet, DropdownConfig $dropdownSettings): void
    {
        $column = $this->getColumnByHeading($worksheet, $dropdownSettings->getField());
        $highestRow = $worksheet->getHighestDataRow($column);

        $metaDataSheet = $this->getMetaDataSheet($worksheet->getParent());
        $foreignModelShort = $this->getModelShortname($dropdownSettings->getFkModel());

        $refIdCol = $this->getColumnByHeading($metaDataSheet, $foreignModelShort.'.id');
        $refValCol = $this->getColumnByHeading($metaDataSheet,
            $foreignModelShort.'.'.$dropdownSettings->getFkTextCol()
        );

        $highestRefValRow = $metaDataSheet->getHighestDataRow($refValCol);

        $arrValuesToRowNr = $this->getValuesIndexedArray($metaDataSheet, $refValCol, 2, $highestRefValRow);

        for ($i = 2; $i <= $highestRow; ++$i) {
            $cell = $worksheet->getCell($column.$i);
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
     * @throws PhpSpreadsheetException
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
    public function addDropdownField(Worksheet $worksheet, string $spreadSheetField, ?string $cellValue, string $validationFormula): void
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

    public function getMetaSheetName(): string
    {
        return $this->metaSheetName;
    }
}
