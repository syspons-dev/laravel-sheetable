<?php

namespace Syspons\Sheetable\Tests\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OneToManyRelation extends Model
{
    use HasFactory;

    protected static function newFactory(): OneToManyRelationFactory
    {
        return OneToManyRelationFactory::new();
    }

    public function withRelationDummies()
    {
        return $this->hasMany(WithRelationDummy::class);
    }
}
