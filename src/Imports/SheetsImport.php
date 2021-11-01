<?php

/** @noinspection PhpMultipleClassDeclarationsInspection */

namespace Syspons\Sheetable\Imports;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Events\BeforeSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Syspons\Sheetable\Models\Contracts\Dropdownable;
use Syspons\Sheetable\Models\Contracts\Sheetable;

class SheetsImport implements ToCollection, WithHeadingRow, WithValidation, WithEvents
{
    private string|Model $modelClass;

    public function __construct(
        string|Model $modelClass,
    ) {
        $this->modelClass = $modelClass;
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
                    $this->handleDropdownFields($dropdownable, $workSheet);
                }
            },
        ];
    }

    /**
     * Handles validation/dropdown-fields getDropdownFields.
     *
     * @param Dropdownable $dropdownable dropdownable model
     */
    private function handleDropdownFields(Dropdownable $dropdownable, Worksheet $sheet)
    {
        $dropdownFields = $dropdownable::getDropdownFields();
        foreach ($dropdownFields as $dropdownSettings) {
//            TODO
        }
    }

    /**
     * updateOrCreate instance from given row array.
     *
     * @SuppressWarnings(PHPMD.ElseExpression)
     */
    protected function updateOrCreate(array $rowArr)
    {
        $model = $this->modelClass::find($rowArr['id']);
        if ($model) {
            $model->update($rowArr);
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
