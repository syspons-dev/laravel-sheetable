<?php

namespace Syspons\Sheetable\Tests\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Syspons\Sheetable\Exports\DropdownConfig;
use Syspons\Sheetable\Models\Contracts\Dropdownable;
use Syspons\Sheetable\Models\Contracts\Sheetable;

/**
 * Class ModelDummy.
 *
 * @property int $id
 * @property string $title
 * @property string $description
 * @property int|null $relation_main_id
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Relation|null $relation
 * @property Collection|Relation[] $relations
 */
class ModelDummy  extends Model implements Sheetable, Dropdownable
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

    public function relation()
    {
        return $this->belongsTo(Relation::class, 'relation_main_id');
    }

    public function relations()
    {
        return $this->belongsToMany(Relation::class)
            ->withPivot('id');
    }

    protected static function newFactory(): ModelDummyFactory
    {
        return ModelDummyFactory::new();
    }

    /**
     * @return DropdownConfig[]
     */
    public static function getDropdownFields(): array
    {
        return [
            (new DropdownConfig())
                ->setField('relation_main_id')
                ->setFkModel(Relation::class)
                ->setFkTextCol('label'),
            (new DropdownConfig())
                ->setField('relation_additional_id')
                ->setFkModel(Relation::class)
                ->setFkTextCol('label')
                ->setMappingRightOfField('relation_main_id')
                ->setMappingMinFields(5),
        ];
    }
}
