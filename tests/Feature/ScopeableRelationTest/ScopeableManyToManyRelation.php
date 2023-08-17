<?php

namespace Syspons\Sheetable\Tests\Feature\ScopeableRelationTest;

use berthott\Scopeable\Models\Traits\Scopeable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScopeableManyToManyRelation extends Model
{
    use HasFactory, Scopeable;

    public $timestamps = false;

    protected static function newFactory(): ScopeableManyToManyRelationFactory
    {
        return ScopeableManyToManyRelationFactory::new();
    }

    public function with_scopeable_relation_dummies()
    {
        return $this->hasMany(WithScopeableRelationDummy::class);
    }
    
    public static function createInstance(): ScopeableManyToManyRelation
    {
        $instance = self::factory()->create();
        $instance->label = 'relation '.$instance->id;
        $instance->save();
        return $instance;
    }
}
