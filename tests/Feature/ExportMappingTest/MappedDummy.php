<?php

namespace Syspons\Sheetable\Tests\Feature\ExportMappingTest;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Syspons\Sheetable\Models\Contracts\Sheetable;

class MappedDummy extends Model implements Sheetable
{
    use HasFactory;

    protected $fillable = [
        'title',
        'date_time_start',
        'date_time_end',
        'one',
        'two',
        'first_name',
        'last_name',
    ];

    public $timestamps = false;

    public static function importRules(): array
    {
        return [
            'title' => 'required',
            'date_time' => 'required|date',
        ];
    }

    public static function exportMapping(): array
    {
        return [
            'id',
            'date_time' => [
                'select' => ['date_time_start', 'date_time_end'],
                'map' => function($date_time_start, $date_time_end) { 
                    $start = (new Carbon($date_time_start))->format('d.m.Y');
                    $end = (new Carbon($date_time_end))->format('d.m.Y');
                    return "$start - $end";
                },
            ],
            'two',
            'one',
            'name' => [
                'select' => ['first_name', 'last_name'],
                'map' => fn($first_name, $last_name) => "$first_name $last_name",
            ]
        ];
    }
}
