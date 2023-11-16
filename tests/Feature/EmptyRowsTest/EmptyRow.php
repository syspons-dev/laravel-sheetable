<?php

namespace Syspons\Sheetable\Tests\Feature\EmptyRowsTest;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Syspons\Sheetable\Models\Contracts\Sheetable;

class EmptyRow extends Model implements Sheetable
{
    use HasFactory;

    protected $fillable = [
        'title',
        'date_time'
    ];

    public $timestamps = false;

    public static function importRules(): array
    {
        return [
            'title' => 'required',
            'date_time' => 'required|date',
        ];
    }

    protected static function newFactory(): EmptyRowFactory
    {
        return EmptyRowFactory::new();
    }
}
