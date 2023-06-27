<?php

namespace Syspons\Sheetable\Models\Contracts;

/**
 * Interface to add the sheetable functionality.
 * 
 * @method array routeOptions() Returns an array of route options.
 * 
 * **optional**
 * 
 * Defaults to `[]`.
 * 
 * @link https://laravel.com/docs/10.x/controllers#api-resource-routes Route::apiResource
 * @see \Syspons\Sheetable\SheetableServiceProvider::$routes
 * @api
 */
interface Sheetable
{
    /**
     * The validation rules that should be used for the import.
     * 
     * **required**
     * 
     * In addition to Laravels usual vialidation rules you can use
     * `exists_strict` {@see \Syspons\Sheetable\SheetableServiceProvider::extendValidator()}
     * 
     * @api
     */
    public static function importRules(): array;
}
