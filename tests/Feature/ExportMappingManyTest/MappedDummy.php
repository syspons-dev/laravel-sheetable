<?php

namespace Syspons\Sheetable\Tests\Feature\ExportMappingManyTest;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Syspons\Sheetable\Exports\Join;
use Syspons\Sheetable\Models\Contracts\Sheetable;

class MappedDummy extends Model implements Sheetable
{
    use HasFactory;

    protected $fillable = [];

    public $timestamps = false;

    public static function importRules(): array
    {
        return [];
    }

    public function joinable_relations()
    {
        return $this->hasMany(JoinableRelation::class);
    }

    public static function getJoins(): array 
    {
        return [
            new Join(
                parent: static::class,
                relation: 'joinable_relations',
                nested: [
                    new Join(
                        parent: JoinableRelation::class,
                        relation: 'joinable_nested_relations',
                    ),
                ],
            ),
        ];
    }

    public static function exportMapping(): array
    {
        return [
            'id',
            'combined' => [
                'select' => ['joinable_relations.first', 'joinable_relations.second'],
                'map' => fn($first, $second) => "$first $second",
            ],
            'combined_translatable' => [
                'select' => ['joinable_relations.translatable_first_translatable_content_id', 'joinable_relations.translatable_second_translatable_content_id'],
                'map' => fn($first, $second) => "$first $second",
            ],
            'nested' => [
                'select' => ['joinable_relations.joinable_nested_relations.first', 'joinable_relations.joinable_nested_relations.second'],
                'map' => fn($first, $second) => "$first $second",
            ],
        ];
    }
}
