<?php

namespace Syspons\Sheetable\Tests\Feature\JoinHasManyNestedTest;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Syspons\Sheetable\Models\Contracts\Sheetable;

class JoinableRelation extends Model implements Sheetable
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

    protected static function newFactory(): JoinableRelationFactory
    {
        return JoinableRelationFactory::new();
    }

    public function joinable_dummy()
    {
        return $this->belongsTo(JoinableDummy::class);
    }

    public function nested_joinable_relations()
    {
        return $this->hasMany(NestedJoinableRelation::class);
    }
}