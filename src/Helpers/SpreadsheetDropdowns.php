<?php

namespace Syspons\Sheetable\Helpers;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Sheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Exception as PhpSpreadsheetException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Syspons\Sheetable\Exceptions\ExcelExportValidationException;
use Syspons\Sheetable\Exports\DropdownConfig;
use Syspons\Sheetable\Models\Contracts\Dropdownable;

/**
 * Helper class for interaction with dropdowns
 */
class SpreadsheetDropdowns
{
    /**
     * The maximum number of columns for a many to many relation.
     * 
     * @var int
     */
    private const MAX_MANY_TO_MANYS = 100;

    /**
     * The maximum number of rows in a dropdown.
     * 
     * @var int
     */
    private const ADD_DROPDOWN_FIELDS_NUM = 200;

    private string $metaSheetName = 'metadata';

    private function getMetaSheetName(): string
    {
        return $this->metaSheetName;
    }

    public function __construct(private SpreadsheetUtils $utils) {}

    /**
     * Import many to many relationships.
     * 
     * A many to many relationship, is represented by multiple columns,
     * that will be processed individually.
     */
    public function importManyToManyPivotEntries(Collection &$collection, string $target)
    {
        if (!method_exists($target, 'getDropdownFields')) {
            return [];
        }

        foreach ($target::getDropdownFields() as $dropdownField) {
            if ($dropdownField->getMappingRightOfField()) {
                $many_to_many_pivots = [];
                $field = $dropdownField->getField();
                $relatedFk = $dropdownField->getForeignKey();
                $modelInstance = $target::newModelInstance();
                $pivotTable = $modelInstance->joiningTable($dropdownField->getFkModel());

                for ($i = 1; $i < self::MAX_MANY_TO_MANYS; ++$i) {
                    $key = $field.'_'.$i;

                    // all items defined in table
                    $items = $collection->filter(function ($item) use ($key) {
                        return $item->has($key);
                    });
                    if (!$items->count()) {
                        break;
                    }

                    array_push(
                        $many_to_many_pivots,
                        // only items with set values
                        ...$items
                            ->filter(function ($item) use ($key) {
                                return !empty($item[$key]);
                            })
                            ->map(function ($item) use ($modelInstance, $relatedFk, $key) {
                                return [
                                    $modelInstance->getForeignKey() => $item[$modelInstance->getKeyName()],
                                    $relatedFk => $item[$key],
                                ];
                            })->all(),
                    );

                    $collection = $collection->map(function ($item) use ($key) {
                        return $item->except($key);
                    });

                    DB::table($pivotTable)->whereIn($modelInstance->getForeignKey(), $collection->pluck($modelInstance->getKeyName()))->delete();
                    DB::table($pivotTable)->insert($many_to_many_pivots);
                }
            }
        }
    }

    /**
     * Adds validation/dropdown-fields for all recipes defined in getDropdownFields.
     *
     * @throws PhpSpreadsheetException
     */
    public function exportDropdownFields(Dropdownable|Model $dropdownable, Worksheet $worksheet, Collection $models)
    {
        $dropdownFields = $dropdownable::getDropdownFields();
        foreach ($dropdownFields as $dropdownConfig) {
            //SheetableLog::log("Exporting dropdown field {$dropdownConfig->getField()}");
            if ($dropdownConfig->isEmbedded()) {
                $this->addForeignKeyDropdownColumnEmbedded($worksheet, $dropdownConfig);
                continue;
            } elseif (0 < $dropdownConfig->getMappingMinFields()) {
                $this->createRefColumnsForField($worksheet->getParent(), $dropdownConfig);
                $this->addManyToManyColumnsForField($worksheet, $dropdownable, $dropdownConfig, $models);
                continue;
            } elseif (0 < count($dropdownConfig->getFixedList())) {
                $this->createRefColumnsForFixedField($worksheet->getParent(), $dropdownConfig);
                $this->addFixedListDropdownColumn($worksheet, $dropdownConfig);
                continue;
            }
            $this->createRefColumnsForField($worksheet->getParent(), $dropdownConfig);
            $this->addForeignKeyDropdownColumn($worksheet, $dropdownConfig);
        }
    }

    /************************************************************
     * methods creating reference fields in metadata sheet
     ************************************************************/

    /**
     * Creates reference (id + describing value) columns within metadata sheet.
     *
     * @throws PhpSpreadsheetException
     */
    private function createRefColumnsForField(Spreadsheet $spreadsheet, DropdownConfig $config)
    {
        $metaDataSheet = $this->getMetaDataSheet($spreadsheet);
        $foreignModelShort = $this->utils->getModelShortname($config->getFkModel());
        $foreignModelCol = $this->utils->getColumnByHeading($metaDataSheet, $foreignModelShort.'.id');

        if (!$foreignModelCol) {
            $metaIdCol = $this->utils->getFirstEmptyColumnName($metaDataSheet);
            $metaValueCol = $this->utils->getNextCol($metaIdCol);
            $row = 1;

            /** @var $descriptions array */
            $descriptions = $config->getFkModel()::select([$config->getFkIdCol(), $config->getFkTextCol()])->get();

            // Set Headings
            $metaDataSheet->setCellValue($metaIdCol.$row, $foreignModelShort.'.id');
            $metaDataSheet->setCellValue(
                $metaValueCol.$row,
                $foreignModelShort.'.'.$config->getFkTextCol()
            );

            foreach ($descriptions as $description) {
                ++$row;
                $id = $description[$config->getFkIdCol()];
                $value = $description->toArray()[$config->getFkTextCol()];
                $metaDataSheet->setCellValue($metaIdCol.$row, $id);
                $metaDataSheet->setCellValue($metaValueCol.$row, $value);
            }
        }
    }

    /**
     * Creates reference (no IDs, describing value only) columns within metadata sheet.
     *
     * @throws PhpSpreadsheetException
     */
    private function createRefColumnsForFixedField(Spreadsheet $spreadsheet, DropdownConfig $config)
    {
        $metaDataSheet = $this->getMetaDataSheet($spreadsheet);
        $metaFixedValCol = $this->utils->getFirstEmptyColumnName($metaDataSheet);
        $row = 1;

        // Set Heading
        $metaDataSheet->setCellValue($metaFixedValCol.$row, 'fixed.'.$config->getField());

        foreach ($config->getFixedList() as $item) {
            ++$row;
            $metaDataSheet->setCellValue($metaFixedValCol.$row, $item);
        }
    }

    /************************************************************
     * methods adding dropdown functionality for a column
     ************************************************************/

    /**
     * Adds a relational dropdown column.
     * 
     * Adds / replaces the IDs of all fields - containing values - in a given column
     * with the corresponding describing text field and adds a dropdown to all of these fields
     * containing the valid values, referencing a range in the metadata-sheet
     * Uses the DropdownConfig-Object to determine the Field and the recipes.
     *
     * @throws PhpSpreadsheetException
     */
    private function addForeignKeyDropdownColumn(Worksheet $sheet, DropdownConfig $dropdownConfig)
    {
        $column = $this->utils->getColumnByHeading($sheet, $dropdownConfig->getField());
        $highestRow = $sheet->getHighestDataRow($column);
        $metaDataSheet = $this->getMetaDataSheet($sheet->getParent());

        $foreignModelShort = $this->utils->getModelShortname($dropdownConfig->getFkModel());

        $this->createRefColumnsForField($sheet->getParent(), $dropdownConfig);
        $refValCol = $this->utils->getColumnByHeading(
            $metaDataSheet,
            $foreignModelShort.'.'.$dropdownConfig->getFkTextCol()
        );

        $highestValRow = $metaDataSheet->getHighestDataRow($refValCol);
        $selectOptions = $this->getMetaSheetName().'!$'.$refValCol.'$2:$'.$refValCol.'$'.$highestValRow;

        for ($i = 2; $i <= $highestRow + self::ADD_DROPDOWN_FIELDS_NUM; ++$i) {
            $this->addForeignKeyDropdownField(
                $sheet,
                $i,
                $dropdownConfig,
                $selectOptions
            );
        }
    }

    /**
     * Adds an embedded relational dropdown column.
     * 
     * Adds / replaces the IDs of all fields - containing values - in a given column
     * with the corresponding describing text field and adds a dropdown to these fields
     * containing the valid values, embedded formula values.
     * WARNING: The sum of all chars, including commas must not exceed 255! Gets cut otherwise.
     * Uses the DropdownConfig-Object to determine the Field and the recipes.
     *
     * @throws PhpSpreadsheetException
     */
    private function addForeignKeyDropdownColumnEmbedded(Sheet|Worksheet $sheet, DropdownConfig $dropdownConfig)
    {
        $highestRow = $sheet->getHighestRow();
        $selectOptions = $this->getEmbeddedValidationFormulaForForeignModel($dropdownConfig);

        for ($i = 2; $i <= $highestRow + self::ADD_DROPDOWN_FIELDS_NUM; ++$i) {
            $this->addForeignKeyDropdownField(
                $sheet,
                $i,
                $dropdownConfig,
                $selectOptions
            );
        }
    }

    /**
     * Adds a fixed dropdown column.
     * 
     * Adds dropdown fields to a column referencing a range in the metadata-sheet
     * Uses the DropdownConfig-Object to determine the Field and the recipes.
     *
     * @throws PhpSpreadsheetException
     */
    private function addFixedListDropdownColumn(Worksheet $worksheet, DropdownConfig $dropdownConfig)
    {
        $column = $this->utils->getColumnByHeading($worksheet, $dropdownConfig->getField());
        $highestRow = $worksheet->getHighestDataRow($column);
        $selectOptions = $this->getValueCoordinatesFromMetaColumn($worksheet, 'fixed.'.$dropdownConfig->getField());

        for ($i = 2; $i <= $highestRow + self::ADD_DROPDOWN_FIELDS_NUM; ++$i) {
            $coord = $column.$i;
            $value = $worksheet->getCell($coord)->getValue();
            $this->addDropdownField($worksheet, $coord, $value, $selectOptions);
        }
    }

    /**
     * Add multiple many to many columns.
     * 
     * Adds multiple columns for an n-to-m field - as many columns as relations exist or the defined min amount -
     * and adds / replaces the IDs of all fields - containing values - in a given column
     * with the corresponding describing text field and adds a dropdown to all of these fields
     * containing the valid values, referencing a range in the metadata-sheet
     * Uses the DropdownConfig-Object to determine the Field and the recipes.
     *
     * @throws PhpSpreadsheetException
     * @todo clean up / refactor
     */
    private function addManyToManyColumnsForField(Worksheet $worksheet, Model $model, DropdownConfig $config, Collection $models)
    {
        $rightOfField = $config->getMappingRightOfField();
        $colCoord = $this->utils->getColumnByHeading($worksheet, $rightOfField);
        $firstColCoord = $colCoord;
        $metaDataSheet = $this->getMetadataSheet($worksheet->getParent());

        $fkModelClass = $config->getFkModel();
        $fkModelTable = $fkModelClass::newModelInstance()->getTable();

        $additionalColCount = $this->colCountForManyToManyField($models, $fkModelTable, $config);

        $additionalFieldName = $config->getField() ?: strtolower($config->getFkModel());

        for ($i = 1; $i <= $additionalColCount; ++$i) {
            $worksheet->insertNewColumnBefore(++$colCoord);
            // headings
            $worksheet->setCellValue($colCoord.'1', $additionalFieldName.'_'.$i);
        }

        // iterate over models/rows - fill additional_1-n cols with values
        for ($i = 0; $i < ($models->count() + self::ADD_DROPDOWN_FIELDS_NUM); ++$i) {
            $modelRow = $models->get($i);
            $row = $i + 2;

            $fkModelTable = $fkModelClass::newModelInstance()->getTable();

            // 2-key-problem resolved
            $listOfFkModels = $modelRow?->$fkModelTable()->orderByPivot('id')->get();
            // $listOfFkModels = $this->getFkModelsForField($modelRow, $fkModelTable);

            /** @var Model $fkModel */
            $colCoord = $firstColCoord;

            // iterate over cols
            for ($j = 0; $j < $additionalColCount; ++$j) {
                $fkModel = null;
                if ($listOfFkModels && $listOfFkModels->has($j)) {
                    $fkModel = $listOfFkModels->get($j);
                }

                $foreignModelShort = $this->utils->getModelShortname($config->getFkModel());

                $refValCol = $this->utils->getColumnByHeading(
                    $metaDataSheet,
                    $foreignModelShort.'.'.$config->getFkTextCol()
                );

                $highestValRow = $metaDataSheet->getHighestDataRow($refValCol);
                $validationFormula = $this->getMetaSheetName().'!$'.$refValCol.'$2:$'.$refValCol.'$'.$highestValRow;

                $fkText = $fkModel ? $this->getDescValueForId($fkModel->getKey(), $config) : null;

                $this->addDropdownField($worksheet, ++$colCoord.$row, $fkText, $validationFormula);
            }
        }
    }

    /**
     * Get count of many to many fields for this dropdownable.
     */
    private function colCountForManyToManyField(Collection $allModels, string $fkModelTableName, DropdownConfig $config): int
    {
        $additionalColCount = $config->getMappingMinFields();

        // find out $maxColCount
        foreach ($allModels as $modelRow) {
            $listOfFkModels = $modelRow->$fkModelTableName;
            if ($listOfFkModels && $listOfFkModels->count() > $additionalColCount) {
                $additionalColCount = $listOfFkModels->count();
            }
        }

        return $additionalColCount;
    }

    /**
     * Add a relation dropdown field
     * 
     * @param string $validationFormula e.g. 'foo, bar' or 'metadata!B1:B3'
     *
     * @throws PhpSpreadsheetException
     * @throws ExcelExportValidationException
     * @throws \Exception
     */
    private function addForeignKeyDropdownField(
        Sheet|Worksheet $worksheet,
        int $rowNr,
        DropdownConfig $config,
        string $validationFormula
    ): void {
        $colCoord = $this->utils->getColumnByHeading($worksheet, $config->getField());
        if (null === $colCoord) {
            throw new Exception('Trying to add the foreign key dropdown field '.$config->getField().', but the field '.$config->getField().'does not exist in the db table '.$config->getFkModel()->getTable());
        }
        $cellCoord = $colCoord.$rowNr;
        $fkId = $worksheet->getCell($cellCoord)->getValue();
        $fkText = $this->getDescValueForId($fkId, $config);
        $this->addDropdownField($worksheet, $cellCoord, $fkText, $validationFormula);
    }

    /**
     * Add dropdown field + validation
     * 
     * @param string      $worksheetField    e.g. 'C2'
     * @param string|null $cellValue         the describing value to be displayed, not the corresponding (db) id
     * @param string      $validationFormula e.g. '"Item 1, Item 2, Item 3"' or 'metadata!$C$2:$C$240'
     *
     * @throws PhpSpreadsheetException
     */
    private function addDropdownField(Worksheet $worksheet, string $worksheetField, string|null $cellValue, string $validationFormula): void
    {
        $worksheet->setCellValue($worksheetField, $cellValue);

        $objValidation = $worksheet->getCell($worksheetField)->getDataValidation();
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

    /**
     * Handles validation/dropdown-fields getDropdownFields.
     *
     * @throws PhpSpreadsheetException
     */
    public function importDropdownFields(Dropdownable $dropdownable, Worksheet $sheet)
    {
        $dropdownFields = $dropdownable::getDropdownFields();
        foreach ($dropdownFields as $dropdownConfig) {
            if ($dropdownConfig->getMappingMinFields()) {
                $manyToManyCols = [];
                $i = 1;
                while ($col = $this->utils->getColumnByHeading($sheet, $dropdownConfig->getField().'_'.$i++)) {
                    $manyToManyCols[] = $col;
                }

                foreach ($manyToManyCols as $manyToManyCol) {
                    $this->resolveImportIdsForDropdownColumn($sheet, $dropdownConfig, $manyToManyCol);
                }
                continue;
            }
            $colCoord = $this->utils->getColumnByHeading($sheet, $dropdownConfig->getField());
            $this->resolveImportIdsForDropdownColumn($sheet, $dropdownConfig, $colCoord);
        }
    }

    /**
     * Replaces the descriptive text(dropdown) fields with the corresponding IDs.
     *
     * @throws PhpSpreadsheetException
     */
    private function resolveImportIdsForDropdownColumn(Worksheet $worksheet, DropdownConfig $dropdownConfig, $colCoord): void
    {
        $highestDataRow = $worksheet->getHighestDataRow($colCoord);
        $highestRow = $worksheet->getHighestRow($colCoord);

        for ($i = 2; $i <= $highestRow; ++$i) {
            // remove DataValidation from all fields
            $worksheet->getCell($colCoord.$i)->setDataValidation();
        }

        if (0 < count($dropdownConfig->getFixedList())) {
            return;
        }

        $metaDataSheet = $this->getMetaDataSheet($worksheet->getParent());
        $foreignModelShort = $this->utils->getModelShortname($dropdownConfig->getFkModel());

        $refIdCol = $this->utils->getColumnByHeading($metaDataSheet, $foreignModelShort.'.id');
        $refValCol = $this->utils->getColumnByHeading(
            $metaDataSheet,
            $foreignModelShort.'.'.$dropdownConfig->getFkTextCol()
        );

        $highestRefValRow = $metaDataSheet->getHighestDataRow($refValCol);

        $arrValuesToRowNr = $this->utils->getValuesIndexedArray($metaDataSheet, $refValCol, 2, $highestRefValRow);

        for ($i = 2; $i <= $highestDataRow; ++$i) {
            // Remove validation from cells
            $cell = $worksheet->getCell($colCoord.$i);
            $rawValue = $cell->getValue();

            if (!array_key_exists($rawValue, $arrValuesToRowNr)) {
                // there is no value for this id in the metadata-sheet
                continue;
            }
            $rowNr = $arrValuesToRowNr[$rawValue];

            $resolvedId = $metaDataSheet->getCell($refIdCol.$rowNr)->getValue();
            $cell->setValue($resolvedId);
        }
    }

    /**
     * Build the embedded validation formula.
     *
     * @return string Formula
     */
    private function getEmbeddedValidationFormulaForForeignModel(DropdownConfig $config): string
    {
        $descriptions = $config->getFkModel()::select($config->getFkTextCol())->get();
        $selectOptions = implode(',', $descriptions->pluck($config->getFkTextCol())->toArray());
        if (strlen($selectOptions) > 255) {
            $selectOptions = substr($selectOptions, 0, 255);
        }

        return '"'.$selectOptions.'"';
    }

    /**
     * Get the sheet containing meta data info like field validation references.
     *
     * @throws PhpSpreadsheetException
     */
    private function getMetadataSheet(Spreadsheet $spreadsheet): Worksheet
    {
        $metaSheet = $spreadsheet->getSheetByName($this->metaSheetName);
        if (null === $metaSheet) {
            $metaSheet = new Worksheet($spreadsheet, $this->metaSheetName);
            $spreadsheet->addSheet($metaSheet, 1);
        }
        $metaSheet->getProtection()->setSheet(true);

        return $metaSheet;
    }

    /**
     * Find the range containing the values within the metadata sheet for given meta column name.
     *
     * @param string $metaColumnName e.g. 'User.username'
     *
     * @return string e.g. 'metadata!$B$2:$B$999'
     *
     * @throws PhpSpreadsheetException
     */
    private function getValueCoordinatesFromMetaColumn(Worksheet $sheet, string $metaColumnName): string
    {
        $metaDataSheet = $this->getMetaDataSheet($sheet->getParent());
        $refValCol = $this->utils->getColumnByHeading($metaDataSheet, $metaColumnName);
        $highestValRow = $metaDataSheet->getHighestDataRow($refValCol);

        return $this->getMetaSheetName().'!$'.$refValCol.'$2:$'.$refValCol.'$'.$highestValRow;
    }

    /**
     * Get the reference descriptive string value for given ID.
     *
     * @param int|null $id e.g. 1
     *
     * @return string|null e.g. 'John'
     */
    private function getDescValueForId(int|string|null $id, DropdownConfig $config): ?string
    {
        if (null === $id) {
            return $id;
        }
        $currentModel = $config->getFkModel()::where($config->getFkIdCol(), '=', $id)->first();

        return $currentModel->toArray()[$config->getFkTextCol()];
    }
}
