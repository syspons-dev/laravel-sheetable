<?php

namespace Syspons\Sheetable\Helpers;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Exception as PhpSpreadsheetException;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SpreadsheetUtils
{
    public const FORMAT_DATE_DATETIME = 'dd.mm.yyyy';
    public const FORMAT_NUMBER_COMMA_SEPARATED_DE = '#,##0.00'; // ??
    public const COL_WIDTH_IN_PT = 16;
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
     * @return string[] names of all datetime columns without created_at/updated_at in given model e.g. ['date_start', 'date_end']
     */
    public function getDateTimeCols(Model|string $model): array
    {
        $dateTimeCols = [];

        $tableName = $model::newModelInstance()->getTable();
        foreach (DB::getSchemaBuilder()->getColumnListing($tableName) as $colName) {
            $type = DB::getSchemaBuilder()->getColumnType($tableName, $colName);
            if ('created_at' !== $colName && 'updated_at' !== $colName) {
                if ('datetime' === $type) {
                    $dateTimeCols[] = $colName;
                }
            }
        }

        return $dateTimeCols;
    }

    /**
     * Formats all columns (width etc.); call this at the end of an export.
     *
     * @throws PhpSpreadsheetException
     */
    public function formatAllCols(Worksheet $worksheet)
    {
        $lastColumn = $worksheet->getHighestColumn();
        ++$lastColumn;
        for ($column = 'A'; $column != $lastColumn; ++$column) {
            $cell = $worksheet->getCell($column.'1');
            $val = $cell->getValue();
            $width = self::COL_WIDTH_IN_PT;
            if (self::COL_WIDTH_IN_PT < strlen($val)) {
                $width = strlen($val);
            }
            $worksheet->getColumnDimension($column)->setWidth($width);
        }
    }

    /**
     * @throws PhpSpreadsheetException
     */
    public function formatSpecialFields(Model $model, Worksheet $worksheet)
    {
        $this->formatAllCols($worksheet);
        $worksheet->getPageSetup()->setFitToWidth(1);

        $this->formatExportCols($model, $worksheet);

        $dateTimeCols = $this->getDateTimeCols($model);
        $dateTimeColValues = 0 === count($dateTimeCols) ? [] : $model::select($dateTimeCols)->get();
        $rowNr = 1;

        // set width for all date fields
        foreach ($dateTimeCols as $dateTimeCol) {
            $colCoord = $this->getColumnByHeading($worksheet, $dateTimeCol);
            $worksheet->getColumnDimension($colCoord)->setWidth(self::COL_DATE_WIDTH_IN_PT);
            $worksheet->getColumnDimension($colCoord)->setAutoSize(false);
            $worksheet->getCell($colCoord.'1')->getStyle()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }

        foreach ($dateTimeColValues as $dateTimeColValue) {
            ++$rowNr;
            foreach ($dateTimeCols as $dateTimeCol) {
                $val = $dateTimeColValue[$dateTimeCol];
                $colCoord = $this->getColumnByHeading($worksheet, $dateTimeCol);

                $worksheet->getStyle($colCoord.$rowNr)->getNumberFormat()->setFormatCode(self::FORMAT_DATE_DATETIME);
                $worksheet->setCellValue($colCoord.$rowNr, Date::PHPToExcel($val));
            }
        }
    }

    /**
     * @return string[] names of all datetime columns in given model e.g. ['date_start', 'date_end']
     */
    public function formatExportCols(Model|string $model, Worksheet $worksheet): array
    {
        $dateTimeCols = [];

        $tableName = $model::newModelInstance()->getTable();
        foreach (DB::getSchemaBuilder()->getColumnListing($tableName) as $colName) {
            $colCoord = $this->getColumnByHeading($worksheet, $colName);
            $type = DB::getSchemaBuilder()->getColumnType($tableName, $colName);
            if ('datetime' === $type) {
                $worksheet->getStyle($colCoord.':'.$colCoord)->getNumberFormat()
                    ->setFormatCode(self::FORMAT_DATE_DATETIME);
            } elseif ('bigint' === $type) {
                $worksheet->getStyle($colCoord.':'.$colCoord)->getNumberFormat()
                    ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER);
            } elseif ('integer' === $type) {
                $worksheet->getStyle($colCoord.':'.$colCoord)->getNumberFormat()
                    ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER);
            } elseif ('float' === $type) {
                $worksheet->getStyle($colCoord.':'.$colCoord)->getNumberFormat()
                    ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
            }
        }

        return $dateTimeCols;
    }

    /**
     * returns column e.g. "B". or null.
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

    public function log(?string ...$logItems)
    {
        $line = Carbon::now()->toDateTimeString().': ';

        foreach ($logItems as $logItem) {
            $line .= $logItem.' ';
        }
        $line .= PHP_EOL;
        file_put_contents('tmp.log', $line, FILE_APPEND);
    }

    /**
     * shortens qualified class name like 'App\Models\Dummy' to shortname like 'Dummy'.
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
     * Converts excel date to db dateTime.
     *
     * @param string|null $dateTime excel date / excel string date
     *
     * @return string dateTime-String for db
     */
    public function cleanImportDateTime(?string $dateTime): string
    {
        try {
            if (null === $dateTime) {
                return Carbon::now()->toDateTimeString();
            }
            $dateTime = substr($dateTime, 0, 19);

            if (preg_match('/[0-9]{5}\.[0-9]{0,9}?/', $dateTime) || preg_match('/[0-9]{5}/', $dateTime)) {
                return Carbon::createFromDate(Date::excelToDateTimeObject($dateTime));
            } elseif (10 === strlen($dateTime)) {
                return Carbon::createFromFormat('d.m.Y', $dateTime)->toDateTimeString();
            }

            return Carbon::createFromFormat('d.m.Y H:i:s', $dateTime)->toDateTimeString();
        } catch (\Exception $e) {
            throw new \Exception($dateTime.' is not a valid date. '.$e);
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
}
