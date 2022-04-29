<?php

namespace Syspons\Sheetable\Helpers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Calculation\Exception;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Exception as PhpSpreadsheetException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Syspons\Sheetable\Exceptions\ExcelImportValidationException;
use Syspons\Sheetable\Models\Contracts\Dropdownable;

class SpreadsheetHelper
{
    private string $codebookSheetNameHorizontal = 'codebook-horizontal';
    private string $codebookSheetName = 'codebook';

    private SpreadsheetUtils $utils;
    private SpreadsheetDropdowns $dropdowns;

    public function __construct(SpreadsheetUtils $utils, SpreadsheetDropdowns $dropdowns)
    {
        $this->utils = $utils;
        $this->dropdowns = $dropdowns;
    }

    /**
     * @throws PhpSpreadsheetException
     */
    public function afterSheetExport(Dropdownable|Model $model, Worksheet $worksheet, Collection $models)
    {
        $this->writeCodeBook($model, $worksheet);
        if (method_exists($model, 'getDropdownFields')) {
            $this->dropdowns->exportDropdownFields($model, $worksheet, $models);
        }
        $this->utils->formatSpecialFields($model, $worksheet, $models);
    }

    /**
     * Removes all values, keeps the headings.
     *
     * @throws PhpSpreadsheetException
     */
    public function clearValues(Worksheet $worksheet)
    {
        $colCoord = $worksheet->getHighestDataColumn();
        $colNumMax = Coordinate::columnIndexFromString($colCoord);
        $rowNumMax = $worksheet->getHighestDataRow();

        for ($col = 1; $col <= $colNumMax; ++$col) {
            for ($row = 2; $row <= $rowNumMax; ++$row) {
                $worksheet->getCellByColumnAndRow($col, $row)->setValue(null);
            }
        }
    }

    /**
     * @throws PhpSpreadsheetException
     */
    public function clearStamps(Worksheet $worksheet)
    {
        $colCoord = $worksheet->getHighestDataColumn();
        $colNumMax = Coordinate::columnIndexFromString($colCoord);

        for ($col = 1; $col <= $colNumMax; ++$col) {
            $cell = $worksheet->getCellByColumnAndRow($col, 1);
            if (
                'created_by' === $cell->getValue() ||
                'updated_by' === $cell->getValue() ||
                'created_at' === $cell->getValue() ||
                'updated_at' === $cell->getValue()
            ) {
                $worksheet->getCellByColumnAndRow($col, 1)->setValue(null);
            }
        }
    }

    /**
     * @throws PhpSpreadsheetException
     */
    public function writeCodeBookHorizontal(Model $model, Worksheet $worksheet)
    {
        if (!Schema::hasTable('code_book')) {
            return;
        }

        $codebookSheet = $this->getCodebookSheetHorizontal($worksheet->getParent());

        $lastColumn = $worksheet->getHighestColumn();
        ++$lastColumn;
        $colCoord = 'A';
        $colNum = -1;

        $codebookSheet->setCellValue('A1', 'Feldname');
        $codebookSheet->getCell('A1')->getStyle()->getFont()->setBold(true);

        $codebookSheet->setCellValue('A2', 'Typ');
        $codebookSheet->getCell('A2')->getStyle()->getFont()->setBold(true);

        $codebookSheet->setCellValue('A3', 'Erklärung');
        $codebookSheet->getCell('A3')->getStyle()->getFont()->setBold(true);
        $codebookSheet->getRowDimension(3)->setRowHeight(80);

        $codebookSheet->setCellValue('A4', 'Beispiel');
        $codebookSheet->getCell('A4')->getStyle()->getFont()->setBold(true);
        $codebookSheet->getRowDimension(4)->setRowHeight(40);

        $codeBook = DB::table('code_book')
            ->where('table_name', $model->getTable())->get();

        for ($column = 'A'; $column != $lastColumn; ++$column) {
            // Skip first col
            ++$colCoord;
            ++$colNum;

            $cellHeading = $worksheet->getCell($column.'1');
            $cellVal = $worksheet->getCell($column.'2');
            $codebookSheet->setCellValue($colCoord.'1', $codeBook[$colNum]->field_name);
            $codebookSheet->getCell($colCoord.'1')->getStyle()->getFont()->setBold(true);

            $codebookSheet->setCellValue($colCoord.'2', $codeBook[$colNum]->data_type);

            $codebookSheet->setCellValue($colCoord.'3', $codeBook[$colNum]->description);
            $codebookSheet->getCell($colCoord.'3')->getStyle()->getAlignment()->setWrapText(true);

            $codebookSheet->setCellValue($colCoord.'4', $codeBook[$colNum]->example);
            $codebookSheet->getCell($colCoord.'4')->getStyle()->getAlignment()->setWrapText(true);

            $width = 35;
            if ($width < strlen($cellHeading->getValue())) {
                $width = strlen($cellHeading->getValue());
            }
            $codebookSheet->getColumnDimension($column)->setWidth($width);
        }
    }

    /**
     * @throws PhpSpreadsheetException
     */
    public function writeCodeBook(Model $model, Worksheet $worksheet)
    {
        if (!Schema::hasTable('code_book')) {
            return;
        }
        
        $codebookSheet = $this->getCodebookSheet($worksheet->getParent());

        $lastColumn = $worksheet->getHighestColumn();
        ++$lastColumn;
        $rowNum = 1;

        $codebookSheet->setCellValue('A1', 'Feldname');
        $codebookSheet->getCell('A1')->getStyle()->getFont()->setBold(true);

        $codebookSheet->setCellValue('B1', 'Typ');
        $codebookSheet->getCell('B1')->getStyle()->getFont()->setBold(true);

        $codebookSheet->setCellValue('C1', 'Pflichtfeld');
        $codebookSheet->getCell('C1')->getStyle()->getFont()->setBold(true);

        $codebookSheet->setCellValue('D1', 'Erklärung');
        $codebookSheet->getCell('D1')->getStyle()->getFont()->setBold(true);

        $codebookSheet->setCellValue('E1', 'Beispiel');
        $codebookSheet->getCell('E1')->getStyle()->getFont()->setBold(true);

        $codeBook = DB::table('code_book')
            ->where('table_name', $model->getTable())->get();

        foreach ($codeBook as $row) {
            ++$rowNum;
            $codebookSheet->getRowDimension($rowNum)->setRowHeight(40);

            $codebookSheet->setCellValue('A'.$rowNum, $row->field_name);
            $codebookSheet->getCell('A'.$rowNum)->getStyle()->getFont()->setBold(true);
            $codebookSheet->getColumnDimension('A')->setWidth(40);

            $codebookSheet->setCellValue('B'.$rowNum, $row->data_type);
            $codebookSheet->getColumnDimension('B')->setWidth(30);

            $codebookSheet->setCellValue('C'.$rowNum, $row->mandatory);
            $codebookSheet->getColumnDimension('C')->setWidth(30);

            $codebookSheet->setCellValue('D'.$rowNum, $row->description);
            $codebookSheet->getCell('D'.$rowNum)->getStyle()->getAlignment()->setWrapText(true);
            $codebookSheet->getColumnDimension('D')->setWidth(50);

            $codebookSheet->setCellValue('E'.$rowNum, $row->example);
            $codebookSheet->getCell('E'.$rowNum)->getStyle()->getAlignment()->setWrapText(true);
            $codebookSheet->getColumnDimension('E')->setWidth(30);

            $type = strtolower($row->data_type);
            $type = trim($type);

            if (str_starts_with($type, 'date')) {
                $codebookSheet->getStyle('E'.$rowNum)->getNumberFormat()
                    ->setFormatCode(SpreadsheetUtils::FORMAT_DATE_DATETIME);
            } elseif ('bigint' === $type || 'integer' === $type || 'int' === $type) {
                $codebookSheet->getStyle('E'.$rowNum)->getNumberFormat()
                    ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER);
            } elseif ('decimal' === $type || 'float' === $type || 'double' === $type) {
                $codebookSheet->getStyle('E'.$rowNum)->getNumberFormat()
                    ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
            }
        }
    }

    /**
     * get the Sheet containing meta data info like field validation references.
     *
     * @throws PhpSpreadsheetException
     */
    private function getCodebookSheetHorizontal(Spreadsheet $spreadsheet): Worksheet
    {
        $metaSheet = $spreadsheet->getSheetByName($this->codebookSheetNameHorizontal);
        if (null === $metaSheet) {
            $metaSheet = new Worksheet($spreadsheet, $this->codebookSheetNameHorizontal);
            $spreadsheet->addSheet($metaSheet, 1);
        }
        $metaSheet->getProtection()->setSheet(true);

        return $metaSheet;
    }

    /**
     * get the Sheet containing meta data info like field validation references.
     *
     * @throws PhpSpreadsheetException
     */
    private function getCodebookSheet(Spreadsheet $spreadsheet): Worksheet
    {
        $metaSheet = $spreadsheet->getSheetByName($this->codebookSheetName);
        if (null === $metaSheet) {
            $metaSheet = new Worksheet($spreadsheet, $this->codebookSheetName);
            $spreadsheet->addSheet($metaSheet, 1);
        }
        $metaSheet->getProtection()->setSheet(true);

        return $metaSheet;
    }

    /**
     * @throws PhpSpreadsheetException
     */
    public function beforeSheetImport(Model|string $modelClass, Worksheet $worksheet)
    {
        $this->preCheckDocumentBeforeImport($worksheet);
        $this->preProcessDocument($worksheet);
        $this->cleanDateTimes($worksheet, $modelClass);
        /** @var Dropdownable $dropdownable */
        $dropdownable = $modelClass::newModelInstance();
        if (method_exists($modelClass, 'getDropdownFields')) {
            $this->dropdowns->importDropdownFields($dropdownable, $worksheet);
        }
    }

    /**
     * @throws ExcelImportValidationException
     */
    private function preCheckDocumentBeforeImport(Worksheet $worksheet)
    {
        $duplicates = $this->getIdDuplicatesInSheet($worksheet);
        if ($duplicates && !empty($duplicates)) {
            $uvException = new PhpSpreadsheetException(
                'Following IDs appear more than once in the document: '.implode(',', $duplicates)
            );
            throw new ExcelImportValidationException($uvException);
        }
    }

    /**
     * Pre process all fields, use only calculated values, no formulas, trim spaces, remove double spaces.
     *
     * @throws PhpSpreadsheetException
     * @throws Exception
     */
    private function preProcessDocument(Worksheet $worksheet)
    {
        foreach ($worksheet->getRowIterator() as $row) {
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(true);
            foreach ($cellIterator as $cell) {
                $value = $cell->getCalculatedValue();
                if (is_string($value)) {
                    $value = preg_replace('/\s+/', ' ', $value);
                    $value = trim($value);
                    
                    // don't store empty strings
                    if (empty($value)) {
                        $cell->setValue(null);
                    }
                }
                if ($value) {
                    $cell->setValue($value);
                }
            }
        }
    }

    private function getIdDuplicatesInSheet(Worksheet $worksheet)
    {
        $colCoord = $this->utils->getColumnByHeading($worksheet, 'id');
        $highestRow = $worksheet->getHighestDataRow($colCoord);
        $ids = $worksheet->rangeToArray($colCoord.'2:'.$colCoord.$highestRow);
        $idValues = [];
        foreach ($ids as $id) {
            $idValues[] = $id[0];
        }

        return array_unique(array_diff_assoc($idValues, array_unique($idValues)));
    }

    public function cleanDateTimes(Worksheet $worksheet, Model|string $model)
    {
        foreach ($this->utils->getDateTimeCols($model) as $dateTimeCol) {
            $colCoord = $this->utils->getColumnByHeading($worksheet, $dateTimeCol);
            $highestRow = $worksheet->getHighestDataRow($colCoord);
            for ($row = 2; $row <= $highestRow; $row++) {
                $cell = $worksheet->getCell($colCoord.$row);
                $cell->setValue($this->utils->cleanImportDateTime($cell->getValue()));
            }
        }
    }

    public function importCollection(Collection $collection, Model|string $model)
    {
        // TODO: use upsert instead of updateOrCreate -> import ManyToMany should also upsert to the pivot table instead of manually attaching
        // $model::upsert($collection->toArray(), ['id']);
        foreach ($collection as $row) {
            $rowArr = $row->toArray();

            foreach (array_keys($rowArr) as $rowItem) {
                if (
                    !$rowItem ||
                    'created_at' === $rowItem ||
                    'updated_at' === $rowItem ||
                    'created_by' === $rowItem ||
                    'updated_by' === $rowItem) {
                    unset($rowArr[$rowItem]);
                }
            }

            $arr = $this->dropdowns->importManyToManyFields($rowArr, $model);
            if ($arr && array_key_exists('rowArr', $arr)) {
                $rowArr = $arr['rowArr'];
            }

            $storedModel = $this->updateOrCreate($rowArr, $model);

            if ($storedModel && $arr && array_key_exists('attachToFields', $arr)) {
                $this->dropdowns->attachManyToManyValues($storedModel, $arr['attachToFields']);
            }
        }
    }

    /**
     * updateOrCreate instance from given row array.
     *
     * @SuppressWarnings(PHPMD.ElseExpression)
     */
    public function updateOrCreate(array $rowArr, Model|string $modelClass)
    {
        return $modelClass::updateOrCreate(['id' => $rowArr['id']], $rowArr);
    }
}
