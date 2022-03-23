<?php

namespace Syspons\Sheetable\Tests\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
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

    public $timestamps = false;

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

    public function one_to_many_relation()
    {
        return $this->belongsTo(OneToManyRelation::class);
    }

    public function many_to_many_relations()
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
                ->setField('one_to_many_relation_id')
                ->setFkModel(OneToManyRelation::class)
                ->setFkTextCol('label'),
            (new DropdownConfig())
                ->setField('many_to_many_relation_id')
                ->setFkModel(ManyToManyRelation::class)
                ->setFkTextCol('label')
                ->setMappingRightOfField('one_to_many_relation_id')
                ->setMappingMinFields(5),
        ];
    }
    
    public static function createInstances(int $count = 3): Collection
    {
        return self::factory()->count($count)->for(OneToManyRelation::factory(), 'one_to_many_relation')->create()->each(function ($item, $key) {
            $item->title = 'test '.++$key;
            $item->description = 'description '.++$key;
            $item->one_to_many_relation->label = 'one_to_many';
            $item->one_to_many_relation->save();
            $item->many_to_many_relations()->attach(ManyToManyRelation::factory()->count(3)->create()->each(function ($item, $rel_key) use ($key) {
                $item->label = 'many_to_many '.$key.++$rel_key;
                $item->save();
            }));
            $item->save();
        });
    }
}
