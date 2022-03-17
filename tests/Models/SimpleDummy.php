<?php

namespace Syspons\Sheetable\Tests\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use JetBrains\PhpStorm\ArrayShape;
use Syspons\Sheetable\Models\Contracts\Sheetable;

class SimpleDummy extends Model implements Sheetable
{
    use HasFactory;

    protected $fillable = [
        'title',
    ];

    public $timestamps = false;

    public static function importRules(): array
    {
        return [
            'title' => 'required',
        ];
    }

    protected static function newFactory(): SimpleDummyFactory
    {
        return SimpleDummyFactory::new();
    }
}
