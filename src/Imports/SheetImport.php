<?php

/** @noinspection PhpMultipleClassDeclarationsInspection */

namespace Syspons\Sheetable\Imports;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Events\BeforeSheet;
use Syspons\Sheetable\Models\Contracts\Dropdownable;
use Syspons\Sheetable\Models\Contracts\Sheetable;
use Syspons\Sheetable\Services\SpreadsheetHelper;

class SheetImport implements ToCollection, WithHeadingRow, WithValidation, WithEvents, SkipsEmptyRows
{
    private string|Model $modelClass;
    private SpreadsheetHelper $helper;

    public function __construct(
        string|Model $modelClass,
        SpreadsheetHelper $helper
    ) {
        $this->modelClass = $modelClass;
        $this->helper = $helper;
    }

    public function collection(Collection $collection)
    {
        // TODO AJ use a better way to identify datetime colmuns
        foreach ($collection as $row) {
            $rowArr = $row->toArray();

            $rowArr['date_start'] = $this->helper->cleanDateTime($row['date_start']);
            $rowArr['date_end'] = $this->helper->cleanDateTime($row['date_end']);

            $this->updateOrCreate($rowArr);
        }
    }

    public function registerEvents(): array
    {
        return [
            BeforeSheet::class => function (BeforeSheet $event) {
                $sheet = $event->sheet;
                $workSheet = $sheet->getDelegate();

                /** @var Dropdownable $dropdownable */
                $dropdownable = $this->modelClass::newModelInstance();
                if (method_exists($this->modelClass, 'getDropdownFields')) {
                    $this->helper->importDropdownFields($dropdownable, $workSheet);
                }
            },
        ];
    }

    /**
     * updateOrCreate instance from given row array.
     *
     * @SuppressWarnings(PHPMD.ElseExpression)
     */
    protected function updateOrCreate(array $rowArr)
    {
        $keyName = app($this->modelClass)->getKeyName();
        /** @var Model $model */
        $model = $this->modelClass::find($rowArr[$keyName]);
        if ($model) {
            unset($rowArr['created_at']);
            $rowArr['updated_at'] = Carbon::now()->toDateTimeString();
            DB::table($model->getTable())
                ->where($keyName, $rowArr[$keyName])
                ->update($rowArr);
        } else {
            $rowArr['created_at'] = Carbon::now()->toDateTimeString();
            $rowArr['updated_at'] = Carbon::now()->toDateTimeString();
            $this->modelClass::insert($rowArr);
        }
    }

    public function rules(): array
    {
        /** @var Sheetable $sheetable */
        $sheetable = $this->modelClass::newModelInstance();

        return $sheetable::importRules();
    }
}
