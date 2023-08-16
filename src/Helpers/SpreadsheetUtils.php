<?php

namespace Syspons\Sheetable\Helpers;

use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Grammars\MySqlGrammar;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Exception as PhpSpreadsheetException;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Syspons\Sheetable\Exceptions\ExcelImportDateValidationException;

/**
 * Helper class for spread sheet utility functions
 */
class SpreadsheetUtils
{
    /**
     * @var string date format
     */
    public const FORMAT_DATE_DATETIME = 'dd.mm.yyyy';

    /**
     * @var int column width in points
     */
    public const COL_WIDTH_IN_PT = 16;

    /**
     * @var int date column width in points
     */
    public const COL_DATE_WIDTH_IN_PT = 16;

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
    public function getNextCol(string $colName): string
    {
        $colIndex = Coordinate::columnIndexFromString($colName);

        return Coordinate::stringFromColumnIndex($colIndex + 1);
    }

    /**
     * Get an array of all the table columns.
     *
     * @return string[]
     */
    public function getDBColumns(Model|string $model): array
    {
        return DB::getSchemaBuilder()->getColumnListing($model::newModelInstance()->getTable());
    }

    /**
     * Get an array of all datetime columns.
     * 
     * Option to include created_at/updated_at.
     * 
     * @return string[] e.g. ['date_start', 'date_end']
     */
    public function getDateTimeCols(Model|string $model, bool $inclCreateUpd = false): array
    {
        $dateTimeCols = [];

        $tableName = $model::newModelInstance()->getTable();
        foreach (DB::getSchemaBuilder()->getColumnListing($tableName) as $colName) {
            $type = DB::getSchemaBuilder()->getColumnType($tableName, $colName);
            if ($inclCreateUpd || ('created_at' !== $colName && 'updated_at' !== $colName)) {
                if ('datetime' === $type) {
                    $dateTimeCols[] = $colName;
                }
            }
        }

        return $dateTimeCols;
    }

    /**
     * Formats all columns.
     * 
     * Call this at the end of an export.
     *
     * @throws PhpSpreadsheetException
     */
    public function formatColumns(Model $model, Worksheet $worksheet, Collection $models)
    {
        $this->setColumnWidth($worksheet);
        $worksheet->getPageSetup()->setFitToWidth(1);
        $this->setColumnFormat($model, $worksheet);
        $this->formatDateColumns($model, $worksheet, $models);
    }

    /**
     * Set the column width to match it's content.
     *
     * @throws PhpSpreadsheetException
     */
    private function setColumnWidth(Worksheet $worksheet)
    {
        $lastColumn = $worksheet->getHighestDataColumn();
        ++$lastColumn;
        for ($column = 'A'; $column != $lastColumn; ++$column) {
            $cell = $worksheet->getCell($column.'1');
            $val = $cell->getValue();
            $width = self::COL_WIDTH_IN_PT;
            if (self::COL_WIDTH_IN_PT < strlen($val)) {
                $width = strlen($val);
            }
            if (!$val) {
                $worksheet->removeColumn($column);
            }
            $worksheet->getColumnDimension($column)->setWidth($width);
        }
    }

    /**
     * Special formatting for date columns.
     * 
     * @throws PhpSpreadsheetException
     */
    private function formatDateColumns(Model $model, Worksheet $worksheet, Collection $models)
    {
        $dateTimeCols = $this->getDateTimeCols($model, true);
        $rowNr = 1;

        // set width
        foreach ($dateTimeCols as $dateTimeCol) {
            $colCoord = $this->getColumnByHeading($worksheet, $dateTimeCol);
            $worksheet->getColumnDimension($colCoord)->setWidth(self::COL_DATE_WIDTH_IN_PT);
            $worksheet->getColumnDimension($colCoord)->setAutoSize(false);
            $worksheet->getCell($colCoord.'1')->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }

        // set format
        foreach ($models as $singleModel) {
            ++$rowNr;
            foreach ($dateTimeCols as $dateTimeCol) {
                $val = $singleModel[$dateTimeCol];
                $colCoord = $this->getColumnByHeading($worksheet, $dateTimeCol);

                $worksheet->getStyle($colCoord.$rowNr)->getNumberFormat()->setFormatCode(self::FORMAT_DATE_DATETIME);
                if ($val) {
                    $worksheet->setCellValue($colCoord.$rowNr, Date::PHPToExcel($val));
                }
            }
        }
    }

    /**
     * Set the column format.
     */
    private function setColumnFormat(Model|string $model, Worksheet $worksheet)
    {
        $tableName = $model::newModelInstance()->getTable();
        foreach (DB::getSchemaBuilder()->getColumnListing($tableName) as $colName) {
            $colCoord = $this->getColumnByHeading($worksheet, $colName);
            $type = DB::getSchemaBuilder()->getColumnType($tableName, $colName);
            $format = '';
            switch($type) {
                case 'datetime':
                    $format = self::FORMAT_DATE_DATETIME;
                    break;
                case 'bigint':
                case 'integer':
                    $format = NumberFormat::FORMAT_NUMBER;
                    break;
                case 'float':
                    $format = NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1;
                    break;
            }
            if ($format) {
                $worksheet->getStyle($colCoord.':'.$colCoord)->getNumberFormat()
                        ->setFormatCode($format);
            }
        }
    }

    /**
     * Get the columns by its heading.
     * 
     * @return ?string e.g. "B". or null.
     */
    public function getColumnByHeading(Worksheet $worksheet, string $heading): ?string
    {
        $row = $worksheet->getRowIterator()->current();
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
     * Shortens qualified class name like 'App\Models\Dummy' to shortname like 'Dummy'.
     *
     * @param string|null $model e.g. App\Models\Dummy
     *
     * @return string e.g. Dummy
     */
    public function getModelShortname(?string $model): string
    {
        $path = explode('\\', $model);

        return array_pop($path);
    }

    /**
     * Converts excel date to DB dateTime.
     *
     * @param string|null $dateTime excel date / excel string date
     *
     * @return string dateTime-String for DB
     */
    public function cleanImportDateTime(?string $dateTime, int $row, string $attribute): string
    {
        try {
            $dateTime = substr($dateTime, 0, 19);
            if (preg_match('/[0-9]{5}\.[0-9]{0,9}?/', $dateTime) || preg_match('/[0-9]{5}/', $dateTime)) {
                return Carbon::createFromDate(Date::excelToDateTimeObject($dateTime));
            } elseif (10 === strlen($dateTime)) {
                return Carbon::createFromFormat('d.m.Y', $dateTime)->toDateTimeString();
            }

            return Carbon::createFromFormat('d.m.Y H:i:s', $dateTime)->toDateTimeString();
        } catch (\Exception $e) {
            throw new ExcelImportDateValidationException($row, $attribute);
        }
    }

    /**
     * @param string $colCoord e.g. A
     * @param int    $startRow e.g. 2
     * @param int    $endRow   e.g. 99
     *
     * @return array eg [ 'SomeValue' => 2]
     *
     * @throws PhpSpreadsheetException
     */
    public function getValuesIndexedArray(Worksheet $worksheet, string $colCoord, int $startRow, int $endRow): array
    {
        $arr = [];
        for ($i = $startRow; $i <= $endRow; ++$i) {
            $val = $worksheet->getCell($colCoord.$i)->getValue();
            $arr[$val] = $i;
        }

        return $arr;
    }

    /**
     * Column name listing for exported models.
     * 
     * If {@link selected} is set, only the selected attributes will be included.
     * 
     * @link https://docs.laravel-excel.com/3.1/exports/mapping.html#adding-a-heading-row
     */
    public function getOrdinalColumnNames($tableName): array
    {
        try {
            return collect(
                DB::select(
                    (new MySqlGrammar)->compileColumnListing().' order by ordinal_position',
                    [DB::getDatabaseName(), $tableName]
                )
            )->pluck('column_name')->toArray();
        } catch (Exception $e) {
            return Schema::getColumnListing($tableName);
        }
    }
}
