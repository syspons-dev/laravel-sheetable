<?php

namespace Syspons\Sheetable\Tests\Feature\ExportMappingManyTest;

use berthott\Translatable\Models\Traits\Translatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Syspons\Sheetable\Models\Contracts\Sheetable;

class JoinableNestedRelation extends Model implements Sheetable
{
    use HasFactory;

    protected $fillable = [
        'first',
        'second',
        'joinable_relation_id',
    ];

    public $timestamps = false;

    public static function importRules(): array
    {
        return [];
    }

    public function joinable_relation()
    {
        return $this->belongsTo(JoinableRelation::class);
    }
}
