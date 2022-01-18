<?php

namespace Syspons\Sheetable\Tests;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Syspons\Sheetable\Models\Contracts\Sheetable;

/**
 * Class Country.
 *
 * @property int                     $id
 * @property string                  $label
 * @property int|null                $created_by
 * @property int|null                $updated_by
 * @property Carbon|null             $created_at
 * @property Carbon|null             $updated_at
 * @property Collection|ModelDummy[] $model_dummies
 */
class Country extends Model implements Sheetable
{
    use HasFactory;

    protected $table = 'countries';

    protected $casts = [
        'created_by' => 'int',
        'updated_by' => 'int',
    ];

    /**
     * Returns an array of dependencies to flush.
     */
    public static function cacheDependencies(): array
    {
        return [
            'dio_events',
            'migov_events',
            'df_missions',
            'dio_projects',
            'rf_missions',
        ];
    }

    protected $fillable = [
        'label',
        'updated_by',
        'created_by',
    ];

    public static function importRules(): array
    {
        return [
            'label' => 'required|min:2',
        ];
    }

    protected static function newFactory(): CountryFactory
    {
        return CountryFactory::new();
    }

    public function model_dummies()
    {
        return $this->hasMany(ModelDummy::class, 'country_main_id');
    }
}
