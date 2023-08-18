<?php

namespace Syspons\Sheetable\Tests\Feature\JoinTest;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Syspons\Sheetable\Exports\JoinConfig;
use Syspons\Sheetable\Models\Contracts\Joinable;
use Syspons\Sheetable\Models\Contracts\Sheetable;

class JoinableDummy extends Model implements Sheetable, Joinable
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description'
    ];

    public $timestamps = false;

    public static function importRules(): array
    {
        return [
            'title' => 'required',
            'description' => 'required',
        ];
    }

    public function joinable_relation()
    {
        return $this->belongsTo(JoinableRelation::class);
    }

    public function another_joinable_relation()
    {
        return $this->belongsTo(AnotherJoinableRelation::class);
    }

    protected static function newFactory(): JoinableDummyFactory
    {
        return JoinableDummyFactory::new();
    }

    public static function getJoins(): array 
    {
        return [
            new JoinConfig(
                entity: JoinableRelation::class,
                relation: 'joinable_relation',
                on: 'joinable_relation_id',
            ),
            new JoinConfig(
                entity: AnotherJoinableRelation::class,
                relation: 'another_joinable_relation',
                on: 'another_joinable_relation_id',
                select: ['another_foreign_field', 'nested_joinable_relation_id'],
                nested: [
                    new JoinConfig(
                        entity: NestedJoinableRelation::class,
                        relation: 'nested_joinable_relation',
                        on: 'nested_joinable_relation_id',
                        select: ['foreign_field'],
                    ),
                ]
            ),
        ];
    }
}
