<?php

namespace Syspons\Sheetable\Tests\Feature\JoinTranslatableTest;

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

    public function one_to_many_translatable_dummy()
    {
        return $this->belongsTo(OneToManyTranslatableDummy::class);
    }

    public function many_to_one_translatable_dummies()
    {
        return $this->hasMany(ManyToOneTranslatableDummy::class);
    }

    public function many_to_many_translatable_dummies()
    {
        return $this->belongsToMany(ManyToManyTranslatableDummy::class);
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
                relation: 'one_to_many_translatable_dummy',
                select: ['title_translatable_content_id'],
            ),
            new Join(
                parent: static::class,
                relation: 'many_to_one_translatable_dummies',
                select: ['title_translatable_content_id'],
            ),
            new Join(
                parent: static::class,
                relation: 'many_to_many_translatable_dummies',
                select: ['title_translatable_content_id'],
            ),
        ];
    }
}
