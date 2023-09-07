<?php

namespace Syspons\Sheetable\Tests\Feature\ExportMappingManyTest;

use berthott\Translatable\Models\Traits\Translatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Syspons\Sheetable\Models\Contracts\Sheetable;

class JoinableRelation extends Model implements Sheetable
{
    use HasFactory;
    use Translatable;

    protected $fillable = [
        'first',
        'second',
        'mapped_dummy_id',
    ];

    public static function translatableFields(): array
    {
        return [
            'translatable_first',
            'translatable_second',
        ];
    }

    public $timestamps = false;

    public static function importRules(): array
    {
        return [];
    }

    public function mapped_dummy()
    {
        return $this->belongsTo(MappedDummy::class);
    }

    public function joinable_nested_relations()
    {
        return $this->hasMany(JoinableNestedRelation::class);
    }
}
