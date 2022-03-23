<?php

namespace Syspons\Sheetable\Tests\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OneToManyRelation extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected static function newFactory(): OneToManyRelationFactory
    {
        return OneToManyRelationFactory::new();
    }

    public function with_relation_dummies()
    {
        return $this->hasMany(WithRelationDummy::class);
    }
}
