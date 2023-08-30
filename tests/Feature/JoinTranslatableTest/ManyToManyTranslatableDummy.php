<?php

namespace Syspons\Sheetable\Tests\Feature\JoinTranslatableTest;

use berthott\Translatable\Models\Traits\Translatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Syspons\Sheetable\Models\Contracts\Sheetable;

class ManyToManyTranslatableDummy extends Model implements Sheetable
{
    use HasFactory;
    use Translatable;

    public static function translatableFields(): array
    {
        return ['title'];
    }

    public $timestamps = false;

    public static function importRules(): array
    {
        //return [];
        return self::translatableRules(null);
    }

    protected static function newFactory(): ManyToManyTranslatableDummyFactory
    {
        return ManyToManyTranslatableDummyFactory::new();
    }

    public function joinable_dummies()
    {
        return $this->belongsToMany(JoinableDummy::class);
    }
}
