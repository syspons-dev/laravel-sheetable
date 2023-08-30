<?php

namespace Syspons\Sheetable\Tests\Feature\JoinBelongsToSelectTest;

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

    public function joinable_select_relation()
    {
        return $this->belongsTo(JoinableSelectRelation::class);
    }

    public function joinable_except_relation()
    {
        return $this->belongsTo(JoinableExceptRelation::class);
    }

    public function joinable_both_relation()
    {
        return $this->belongsTo(JoinableBothRelation::class);
    }

    protected static function newFactory(): JoinableDummyFactory
    {
        return JoinableDummyFactory::new();
    }

    public static function getJoins(): array 
    {
        return [
            new Join(
                parent: static::class,
                relation: 'joinable_select_relation',
                select: ['foreign_field'],
            ),
            new Join(
                parent: static::class,
                relation: 'joinable_except_relation',
                except: ['id', 'another_foreign_field', 'yet_another_foreign_field'],
            ),
            new Join(
                parent: static::class,
                relation: 'joinable_both_relation',
                select: ['foreign_field', 'another_foreign_field'],
                except: ['another_foreign_field'],
            ),
        ];
    }
}
