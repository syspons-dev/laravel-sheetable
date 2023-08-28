<?php

namespace Syspons\Sheetable\Helpers;

use berthott\Scopeable\Facades\Scopeable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\DefaultValueBinder as ExcelDefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Calculation\Exception;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Exception as PhpSpreadsheetException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Syspons\Sheetable\Exceptions\ExcelImportScopeableException;
use Syspons\Sheetable\Exceptions\ExcelImportValidationException;
use Syspons\Sheetable\Imports\ArrayValueBinder;
use Syspons\Sheetable\Models\Contracts\Dropdownable;

/**
 * Helper class for spread sheet interaction
 */
class SpreadsheetHelper
{
    private string $codebookSheetNameHorizontal = 'codebook-horizontal';
    private string $codebookSheetName = 'codebook';

    public function __construct(
        private SpreadsheetUtils $utils, 
        private SpreadsheetDropdowns $dropdowns
    ) {}

    /**
     * Process the worksheet after exporting.
     * 
     * * add the codebook
     * * add dropdown fields
     * * format columns
     * 
     * @throws PhpSpreadsheetException
     */
    public function afterSheetExport(Dropdownable|Model $model, Worksheet $worksheet, Collection $models)
    {
        SheetableLog::log('Writing Codebook...');
        $this->writeCodeBook($model, $worksheet);
        SheetableLog::log('Codebook written.');
        if (method_exists($model, 'getDropdownFields')) {
            SheetableLog::log('Adding dropdown fields...');
            $this->dropdowns->exportDropdownFields($model, $worksheet, $models);
            SheetableLog::log('Dropdown fields added.');
        }
        if (method_exists($model, 'translatableFields')) {
            SheetableLog::log('Adding translatable fields...');
            $this->exportTranslatableFields($model, $worksheet, $models);
            SheetableLog::log('Translatable fields added.');
        }
        SheetableLog::log('Formatting special fields...');
        $this->utils->formatColumns($model, $worksheet, $models);
        SheetableLog::log('Special fields formatted.');
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
     * Clear userstamps and timestamps.
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
     * Write a horizontal codebook.
     * 
     * @deprecated
     * @throws PhpSpreadsheetException
     */
    private function writeCodeBookHorizontal(Model $model, Worksheet $worksheet)
    {
        if (!Schema::hasTable('code_book')) {
            return;
        }

        $codebookSheet = $this->getCodebookSheet($worksheet->getParent(), $this->codebookSheetNameHorizontal);

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
     * Write a codebook.
     * 
     * @throws PhpSpreadsheetException
     */
    private function writeCodeBook(Model $model, Worksheet $worksheet)
    {
        if (!Schema::hasTable('code_book')) {
            return;
        }
        
        $codebookSheet = $this->getCodebookSheet($worksheet->getParent(), $this->codebookSheetName);

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
                    ->setFormatCode(NumberFormat::FORMAT_NUMBER);
            } elseif ('decimal' === $type || 'float' === $type || 'double' === $type) {
                $codebookSheet->getStyle('E'.$rowNum)->getNumberFormat()
                    ->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
            }
        }
    }

    /**
     * Get the sheet containing meta data info like field validation references.
     *
     * @throws PhpSpreadsheetException
     */
    private function getCodebookSheet(Spreadsheet $spreadsheet, string $sheetName): Worksheet
    {
        $metaSheet = $spreadsheet->getSheetByName($sheetName);
        if (null === $metaSheet) {
            $metaSheet = new Worksheet($spreadsheet, $sheetName);
            $spreadsheet->addSheet($metaSheet, 1);
        }
        $metaSheet->getProtection()->setSheet(true);

        return $metaSheet;
    }

    /**
     * Process the worksheet before importing.
     * 
     * * validate duplicates
     * * import dropdown fields
     * * preprocess document
     * * clean date times
     * 
     * @throws PhpSpreadsheetException
     */
    public function beforeSheetImport(Model|string $modelClass, Worksheet $worksheet)
    {
        $this->validateIdDuplicates($worksheet);
        $this->preProcessDocument($worksheet);
        $this->cleanDateTimes($worksheet, $modelClass);
        if (method_exists($modelClass, 'getDropdownFields')) {
            $this->dropdowns->importDropdownFields($modelClass::newModelInstance(), $worksheet);
        }
        if (method_exists($modelClass, 'translatableFields')) {
            $this->importTranslatableFields($modelClass::newModelInstance(), $worksheet);
        }
    }

    /**
     * Find ID duplicates and throw an error.
     * 
     * @throws ExcelImportValidationException
     */
    private function validateIdDuplicates(Worksheet $worksheet)
    {
        $duplicates = $this->getIdDuplicatesInSheet($worksheet);
        if ($duplicates && !empty($duplicates)) {
            $uvException = new PhpSpreadsheetException(
                __('Following IDs appear more than once in the document: ').implode(',', $duplicates)
            );
            throw new ExcelImportValidationException($uvException);
        }
    }

    /**
     * Find ID duplicates.
     * 
     * @return int[]
     */
    private function getIdDuplicatesInSheet(Worksheet $worksheet): array
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

    /**
     * Pre process all fields.
     * 
     * * use only calculated values
     * * no formulas
     * * trim spaces
     * * remove double spaces
     *
     * @throws PhpSpreadsheetException
     */
    private function preProcessDocument(Worksheet $worksheet)
    {
        foreach ($worksheet->getRowIterator() as $row) {
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(true);
            foreach ($cellIterator as $cell) {
                // calculate formulas
                $value = $cell->getCalculatedValue();
                if (is_string($value)) {
                    // remove double spaces
                    $value = preg_replace('/\s+/', ' ', $value);
                    // trim white spaces
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

    /**
     * Converts excel date to DB dateTime.
     */
    private function cleanDateTimes(Worksheet $worksheet, Model|string $model)
    {
        foreach ($this->utils->getDateTimeCols($model) as $dateTimeCol) {
            $colCoord = $this->utils->getColumnByHeading($worksheet, $dateTimeCol);
            $highestRow = $worksheet->getHighestDataRow($colCoord);
            for ($row = 2; $row <= $highestRow; $row++) {
                $cell = $worksheet->getCell($colCoord.$row);
                $cell->setValue($this->utils->cleanImportDateTime($cell->getValue(), $row, $dateTimeCol));
            }
        }
    }

    /**
     * Import the collection to the database.
     */
    public function importCollection(Collection $collection, Model|string $model)
    {
        // TODO the upsert method will bypass model events (breaks userstamps + caching) and will
        // update in any case, so with no changes 'updated_at' will be updated.
        // $model::upsert($this->constrainedToDbColumns($collection, $model)->toArray(), ['id']);
        DB::beginTransaction();
        $this->constrainedToDbColumns($collection, $model)->each(function ($entity, $key) use ($model, $collection) {
            $arr = $entity->toArray();
            $instance = $model::updateOrCreate(['id' => $arr['id']], $arr);

            // this works around missing fillable['id'] and forces the user defined id
            if ($arr['id']) {
                $instance->id = $arr['id'];
                $instance->save();
            }

            $collection[$key]['id'] = $instance->id;
        });
        $this->dropdowns->importManyToManyPivotEntries($collection, $model);
        $commit = true;
        try {
            $collection->each(function($item, $index) use ($model) {
                if ($instance = $model::find($item['id'])) {
                    if (!Scopeable::isAllowedInScopes($instance)) {
                        $commit = false;
                        throw new ExcelImportScopeableException(++$index);
                    }
                }
            });
        } catch (ExcelImportScopeableException $e) {
            DB::rollBack();
            throw $e;
        }
        if ($commit) {
            DB::commit();
        }
    }

    /**
     * Constrain the collection to match the database columns.
     * 
     * Also includes translatable fields when present.
     * 
     * @see importTranslatableFields()
     */
    private function constrainedToDbColumns(Collection $collection, Model|string $model): Collection
    {
        return $collection->map(fn ($item) =>
            $item->only($this->acceptedColumns($model))->except(['created_at', 'updated_at', 'created_by', 'updated_by'])
        );
    }

    /**
     * Returns all accepted columns.
     * 
     * @see constrainedToDbColumns()
     * @see \Syspons\Sheetable\Http\Requests\ExportRequest::rules()
     */
    public function acceptedColumns(Model|string $model): array
    {
        return [
            ...$this->utils->getDBColumns($model),
            ...(method_exists($model, 'translatableFields') ? $model::translatableFields() : []),
        ];
    }

    /**
     * Write all the translations to the worksheet.
     * 
     * Delete the original reference column and show a column for each language instead.
     */
    private function exportTranslatableFields(Model $target, Worksheet $worksheet, Collection $entities)
    {
        foreach ($target::translatableFields() as $translatableField) {
            $column = $currentColumn = $this->utils->getColumnByHeading($worksheet, $translatableField.'_translatable_content_id');
            if (!$column) {
                continue;
            }

            foreach($this->getTranslatableLanguages() as $language) {
                $worksheet->insertNewColumnBefore(++$currentColumn);
                // heading
                $worksheet->setCellValue($currentColumn.'1', $translatableField.'_'.$language);

                // content
                $entities->each(function (Model $entity, int $index) use ($worksheet, $currentColumn, $translatableField, $language) {
                    $rowCount = $index + 2;
                    if (array_key_exists($language, $entity->$translatableField)) {
                        $worksheet->setCellValue($currentColumn.$rowCount, $entity->$translatableField[$language]);
                    }
                });
            }
            $worksheet->removeColumn($column);
        }
    }

    /**
     * Prepare the worksheet for import.
     * 
     * The translation columns will be joined into one column with an array to import 
     * the translations.
     * 
     * @see \Syspons\Sheetable\Imports\SheetImport::prepareForValidation
     * @see constrainedToDbColumns()
     */
    private function importTranslatableFields(Model $target, Worksheet $worksheet)
    {
        foreach ($target::translatableFields() as $translatableField) {
            $column = $this->utils->getColumnByHeading($worksheet, $translatableField.'_'.config('translatable.default_language'));
            if (!$column) {
                continue;
            }

            // set original heading name
            $worksheet->setCellValue($column.'1', $translatableField);

            $highestRow = $worksheet->getHighestRow($column);
            
            // build translation array in first column
            for ($row = 2; $row <= $highestRow; ++$row) {
                $currentColumn = $column;
                $translatable = [];
                foreach($this->getTranslatableLanguages() as $language) {
                    $translatable[$language] = $worksheet->getCell($currentColumn.$row)->getValue();
                    $currentColumn++;
                }
                $cell = $worksheet->getCell($column.$row);
                $cell->setDataValidation();
                $cell->setValue($translatable);
            }
            
            // delete the other columns
            $currentColumn = $column;
            foreach($this->getTranslatableLanguages() as $language) {
                if ($language !== config('translatable.default_language')) {
                    $worksheet->removeColumn($currentColumn);
                }
                $currentColumn++;
            }
        }
    }

    /**
     * Get the columns needed for the translatable field.
     */
    private function getTranslatableLanguages(): array
    {
        return [
            config('translatable.default_language'),
            ...array_filter(array_keys(config('translatable.languages')), fn ($lang) => $lang !== config('translatable.default_language')),
        ];
    }
}
