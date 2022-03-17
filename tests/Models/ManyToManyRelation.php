<?php

namespace Syspons\Sheetable\Tests\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Syspons\Sheetable\Models\Contracts\Sheetable;

class ManyToManyRelation extends Model implements Sheetable
{
    use HasFactory;

    public static function importRules(): array
    {
        return [
            'label' => 'required|min:2',
        ];
    }

    protected static function newFactory(): ManyToManyRelationFactory
    {
        return ManyToManyRelationFactory::new();
    }

    public function with_relation_dummies()
    {
        return $this->hasMany(WithRelationDummy::class, 'many_to_many_relation_main_id');
    }
}
