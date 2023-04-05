<?php

namespace Syspons\Sheetable\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Syspons\Sheetable\Models\Contracts\Sheetable;

class HasExcept extends Model implements Sheetable
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
            'except' => ['import']
        ];
    }
}
