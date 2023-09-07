<?php

namespace Syspons\Sheetable\Tests\Feature\ExportMappingTranslatableTest;

use berthott\Translatable\Models\Traits\Translatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Syspons\Sheetable\Models\Contracts\Sheetable;

class MappedDummy extends Model implements Sheetable
{
    use HasFactory;
    use Translatable;

    protected $fillable = [
        'title',
        'first',
        'second',
    ];

    public $timestamps = false;

    public static function translatableFields(): array
    {
        return [
            'title',
            'first',
            'second',
        ];
    }

    public static function importRules(): array
    {
        return [];
    }

    public static function exportMapping(): array
    {
        return [
            'id',
            'combined' => [
                'select' => ['first_translatable_content_id', 'second_translatable_content_id'],
                'map' => fn($first, $last) => "$first $last",
            ],
            'title_translatable_content_id',
        ];
    }
}
