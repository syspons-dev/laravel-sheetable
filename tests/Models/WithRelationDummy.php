<?php

namespace Syspons\Sheetable\Tests\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Syspons\Sheetable\Exports\DropdownConfig;
use Syspons\Sheetable\Models\Contracts\Dropdownable;
use Syspons\Sheetable\Models\Contracts\Sheetable;

class WithRelationDummy extends Model implements Sheetable, Dropdownable
{
    use HasFactory;
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'title', 'description',
    ];

    public static function rules(mixed $id): array
    {
        return [
            'title' => 'required|min:5',
        ];
    }

    public static function importRules(): array
    {
        return self::rules(null);
    }

    public function oneToManyRelation()
    {
        return $this->belongsTo(OneToManyRelation::class);
    }

    public function manyToManyRelations()
    {
        return $this->belongsToMany(ManyToManyRelation::class);
    }

    protected static function newFactory(): WithRelationDummyFactory
    {
        return WithRelationDummyFactory::new();
    }

    /**
     * @return DropdownConfig[]
     */
    public static function getDropdownFields(): array
    {
        return [
            (new DropdownConfig())
                ->setField('relation_main_id')
                ->setFkModel(ManyToManyRelation::class)
                ->setFkTextCol('label'),
            (new DropdownConfig())
                ->setField('relation_additional_id')
                ->setFkModel(ManyToManyRelation::class)
                ->setFkTextCol('label')
                ->setMappingRightOfField('relation_main_id')
                ->setMappingMinFields(5),
        ];
    }
}