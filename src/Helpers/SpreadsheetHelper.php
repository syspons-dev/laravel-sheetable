<?php

namespace Syspons\Sheetable\Helpers;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Exception as PhpSpreadsheetException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Syspons\Sheetable\Models\Contracts\Dropdownable;

class SpreadsheetHelper
{
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
    public function afterSheetExport(Dropdownable|Model $model, Worksheet $worksheet)
    {
        $this->writeCodeBook($worksheet);

        if (method_exists($model, 'getDropdownFields')) {
            $this->dropdowns->exportDropdownFields($model, $worksheet);
        }
        $this->utils->formatSpecialFields($model, $worksheet);
    }

    /**
     * @throws PhpSpreadsheetException
     */
    public function writeCodeBook(Worksheet $worksheet)
    {
        $codebookSheet = $this->getCodebookSheet($worksheet->getParent());

        $lastColumn = $worksheet->getHighestColumn();
        ++$lastColumn;
        $columnCodebook = 'A';

        $codebookSheet->setCellValue('A1', 'Feldname');
        $codebookSheet->getCell('A1')->getStyle()->getFont()->setBold(true);

        $codebookSheet->setCellValue('A2', 'Typ');
        $codebookSheet->getCell('A2')->getStyle()->getFont()->setBold(true);

        $codebookSheet->setCellValue('A3', 'Beispiel');
        $codebookSheet->getCell('A3')->getStyle()->getFont()->setBold(true);

        $codebookSheet->setCellValue('A4', 'Erklärung');
        $codebookSheet->getCell('A4')->getStyle()->getFont()->setBold(true);

        $codebookSheet->getRowDimension(4)->setRowHeight(80);

        for ($column = 'A'; $column != $lastColumn; ++$column) {
            // Skip first col
            ++$columnCodebook;

            $cellHeading = $worksheet->getCell($column.'1');
            $cellVal = $worksheet->getCell($column.'2');
            $codebookSheet->setCellValue($columnCodebook.'1', $cellHeading->getValue());
            $codebookSheet->getCell($columnCodebook.'1')->getStyle()->getFont()->setBold(true);

            $codebookSheet->setCellValue($columnCodebook.'2', 'string/int/date');

            $example = $cellVal->getValue();
            if (!$example) {
                $example = 'My Example';
            }

            $codebookSheet->setCellValue($columnCodebook.'3', $example);
            $codebookSheet->setCellValue($columnCodebook.'4',
                'This the Description<br>'
                .'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt '
                .'labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores'
            );
            $codebookSheet->getCell($columnCodebook.'4')->getStyle()->getAlignment()->setWrapText(true);

            $width = 30;
            if ($width < strlen($cellHeading->getValue())) {
                $width = strlen($cellHeading->getValue());
            }
            $codebookSheet->getColumnDimension($column)->setWidth($width);
        }
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
        /** @var Dropdownable $dropdownable */
        $dropdownable = $modelClass::newModelInstance();
        if (method_exists($modelClass, 'getDropdownFields')) {
            $this->dropdowns->importDropdownFields($dropdownable, $worksheet);
        }
    }

    public function importCollection(Collection $collection, Model|string $model)
    {
        foreach ($collection as $row) {
            $rowArr = $row->toArray();

            $dateTimeCols = $this->utils->getDateTimeCols($model);

            foreach ($dateTimeCols as $dateTimeCol) {
                $rowArr[$dateTimeCol] = $this->utils->cleanImportDateTime($row[$dateTimeCol]);
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
    protected function updateOrCreate(array $rowArr, Model|string $modelClass)
    {
        $keyName = app($modelClass)->getKeyName();
        /** @var Model $model */
        $model = $modelClass::find($rowArr[$keyName]);
        if ($model) {
            unset($rowArr['created_at']);
            $rowArr['updated_at'] = Carbon::now()->toDateTimeString();
            DB::table($model->getTable())
                ->where($keyName, $rowArr[$keyName])
                ->update($rowArr);

            return $model;
        } else {
            $rowArr['created_at'] = Carbon::now()->toDateTimeString();
            $rowArr['updated_at'] = Carbon::now()->toDateTimeString();
            $id = $modelClass::insertGetId($rowArr);

            return $modelClass::find($id);
        }
    }
}
