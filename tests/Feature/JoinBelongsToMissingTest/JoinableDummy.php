<?php

namespace Syspons\Sheetable\Tests\Feature\JoinBelongsToMissingTest;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Syspons\Sheetable\Exports\Join;
use Syspons\Sheetable\Models\Contracts\Joinable;
use Syspons\Sheetable\Models\Contracts\Sheetable;

class JoinableDummy extends Model implements Sheetable, Joinable
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'joinable_relation_id',
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

    public static function getJoins(): array 
    {
        return [
            new Join(
                parent: static::class,
                relation: 'joinable_relation'
            ),
        ];
    }
}
