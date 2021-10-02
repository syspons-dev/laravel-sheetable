<?php /** @noinspection PhpMultipleClassDeclarationsInspection */

namespace Syspons\Sheetable\Imports;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class SheetsImport implements ToCollection, WithHeadingRow
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
}
