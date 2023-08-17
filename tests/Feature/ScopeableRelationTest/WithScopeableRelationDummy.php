<?php

namespace Syspons\Sheetable\Tests\Feature\ScopeableRelationTest;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Syspons\Sheetable\Exports\DropdownConfig;
use Syspons\Sheetable\Models\Contracts\Dropdownable;
use Syspons\Sheetable\Models\Contracts\Sheetable;

class WithScopeableRelationDummy extends Model implements Sheetable, Dropdownable
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

    public function scopeable_many_to_many_relations()
    {
        return $this->belongsToMany(ScopeableManyToManyRelation::class);
    }

    protected static function newFactory(): WithScopeableRelationDummyFactory
    {
        return WithScopeableRelationDummyFactory::new();
    }

    /**
     * @return DropdownConfig[]
     */
    public static function getDropdownFields(): array
    {
        return [
            (new DropdownConfig())
                ->setField('scopeable_many_to_many_relation_id')
                ->setFkModel(ScopeableManyToManyRelation::class)
                ->setFkTextCol('label')
                ->setMappingRightOfField('description')
                ->setMappingMinFields(1),
        ];
    }
    
    public static function createInstances(int $count = 3): Collection
    {
        return self::factory()->count($count)->create()->each(function ($item) {
            $item->title = 'test '.$item->id;
            $item->description = 'description '.$item->id;
            $item->save();
        });
    }
}
