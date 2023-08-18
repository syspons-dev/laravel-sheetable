<?php

namespace Syspons\Sheetable\Tests\Feature\JoinTest;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Syspons\Sheetable\Models\Contracts\Sheetable;

class AnotherJoinableRelation extends Model implements Sheetable
{
    use HasFactory;

    protected $fillable = [
        'foreign_field',
        'another_foreign_field'
    ];

    public $timestamps = false;

    public static function importRules(): array
    {
        return [
            'foreign_field' => 'required',
            'another_foreign_field' => 'required',
        ];
    }

    protected static function newFactory(): AnotherJoinableRelationFactory
    {
        return AnotherJoinableRelationFactory::new();
    }

    public function joinable_dummies()
    {
        return $this->hasMany(JoinableDummy::class);
    }

    public function nested_joinable_relation()
    {
        return $this->belongsTo(NestedJoinableRelation::class);
    }
}
