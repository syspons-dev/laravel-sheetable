<?php

namespace Syspons\Sheetable\Tests\Feature\RouteOptions;

use Illuminate\Database\Eloquent\Model;
use Syspons\Sheetable\Models\Contracts\Sheetable;

class HasOnly extends Model implements Sheetable
{
    public static function importRules(): array
    {
        return [];
    }

    /**
     * Returns an array of route options.
     * See Route::apiResource documentation.
     */
    public static function routeOptions(): array
    {
        return [
            'only' => ['export'],
        ];
    }
}
