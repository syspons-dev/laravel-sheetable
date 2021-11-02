<?php

/** @noinspection PhpMultipleClassDeclarationsInspection */

namespace Syspons\Sheetable\Imports;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Events\BeforeSheet;
use Syspons\Sheetable\Models\Contracts\Dropdownable;
use Syspons\Sheetable\Models\Contracts\Sheetable;
use Syspons\Sheetable\Services\SpreadsheetHelper;

class SheetImport implements ToCollection, WithHeadingRow, WithValidation, WithEvents
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
        foreach ($collection as $row) {
            $rowArr = $row->toArray();
            $rowArr['created_at'] = $this->cleanDateTime($row['created_at']);
            $rowArr['updated_at'] = $this->cleanDateTime($row['updated_at']);
            $this->updateOrCreate($rowArr);
        }
    }

    private function cleanDateTime(string $dateTime): string
    {
        return Carbon::createFromFormat('Y-m-d\TH:i:s', substr($dateTime, 0, 19))->toDateTimeString();
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
        /** @var Model $model */
        $model = $this->modelClass::find($rowArr['id']);
        if ($model) {
            DB::table($model->getTable())
                ->where('id', $rowArr['id'])
                ->update($rowArr);
        } else {
            $this->modelClass::insert($rowArr);
        }
    }

    public function rules(): array
    {
        /** @var Sheetable $sheetable */
        $sheetable = $this->modelClass::newModelInstance();

        return $sheetable::rules(null);
    }
}
