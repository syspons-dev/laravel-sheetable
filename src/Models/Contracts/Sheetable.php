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
 * 
 * @method array exportMapping() Returns an array columns to export.
 * 
 * * The exact order will be returned. 
 * * Missing columns won't be exported.
 * * Translatable fields must be selected by their id: `field_translatable_content_id`.
 * * Columns can be merged by adding an associative entry providing the selected columns, and a callback.
 *   ```
 *   'combined_column_name' => [
 *     'select' => ['selected_column_1', 'selected_column_2', ...],
 *     'map' => fn($column_1, $column_2) => "$column_1 $column_2",
 *   ],
 *   ```
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
