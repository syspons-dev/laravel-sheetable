<?php

namespace Syspons\Sheetable\Tests\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ManyToManyRelation extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected static function newFactory(): ManyToManyRelationFactory
    {
        return ManyToManyRelationFactory::new();
    }

    public function with_relation_dummies()
    {
        return $this->hasMany(WithRelationDummy::class, 'many_to_many_relation_main_id');
    }
}
