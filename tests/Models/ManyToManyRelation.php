<?php

namespace Syspons\Sheetable\Tests\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ManyToManyRelation extends Model
{
    use HasFactory;

    protected static function newFactory(): ManyToManyRelationFactory
    {
        return ManyToManyRelationFactory::new();
    }

    public function withRelationDummies()
    {
        return $this->hasMany(WithRelationDummy::class, 'many_to_many_relation_main_id');
    }
}
