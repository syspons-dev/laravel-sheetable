<?php

namespace Syspons\Sheetable;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;
use JetBrains\PhpStorm\ArrayShape;
use Syspons\Sheetable\Exceptions\Handler;
use Syspons\Sheetable\Http\Controllers\SheetController;
use Syspons\Sheetable\Services\SheetableService;

class SheetableServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // bind singleton
        $this->app->singleton(SheetableService::class, function () {
            return new SheetableService();
        });

        // bind exception singleton
        $this->app->singleton(ExceptionHandler::class, Handler::class);

        // add config
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'sheetable');
    }

    /**
     * Bootstrap services.
     */
    public function boot(SheetableService $sheetableService): void
    {
        // publish config
        $this->publishes([
            __DIR__.'/../config/config.php' => config_path('sheetable.php'),
        ], 'config');

        // register log channel
        $this->app->make('config')->set('logging.channels.sheetable', [
            'driver' => 'daily',
            'path' => storage_path('logs/sheetable.log'),
            'level' => 'debug',
        ]);

        // add routes
        Route::group($this->routeConfiguration(), function () use ($sheetableService) {
            foreach ($sheetableService->getTargetableClasses() as $sheetableClass) {
                $tableName = $sheetableClass::newModelInstance()->getTable();

                $crudRoutes = $this->getCrudRoutes($sheetableClass);
                if (in_array('export', $crudRoutes)) {
                    Route::match(['get', 'post'], "{$tableName}/export", [SheetController::class, 'export'])->name($tableName.'.export');
                }
                if (in_array('template', $crudRoutes)) {
                    Route::get("{$tableName}/template", [SheetController::class, 'template'])->name($tableName.'.template');
                }
                if (in_array('import', $crudRoutes)) {
                    Route::post("{$tableName}/import", [SheetController::class, 'import'])->name($tableName.'.import');
                }
            }
        });

        // extend validator
        $this->extendValidator();
    }

    #[ArrayShape(['middleware' => 'mixed', 'prefix' => 'mixed'])]
    protected function routeConfiguration(): array
    {
        return [
            'middleware' => config('sheetable.middleware'),
            'prefix' => config('sheetable.prefix'),
        ];
    }

    protected function extendValidator()
    {
        Validator::extend('exists_strict', function ($attribute, $value, $parameters, $validator) {
            if (count($parameters) < 1) {
                throw new \InvalidArgumentException("Validation rule exists_strict requires at least 1 parameter.");
            }

            $collection = $parameters[0];
            $column     = $parameters[1] ?: 'id';

            $entries = DB::table($collection)->where($column, $value)->get();
            if (!$entries->count()) {
                return false;
            }

            foreach ($entries as $entry) {
                if ($entry->id !== $value) {
                    return false;
                }
            }

            return true;
        });
    }

    /**
     * Get the applicable resource methods.
     */
    protected function getCrudRoutes(string $sheetableClass): array
    {
        $methods = ['export', 'template', 'import'];
        if (!method_exists($sheetableClass, 'routeOptions')) {
            return $methods;
        }
        $options = $sheetableClass::routeOptions();
        if (isset($options['only'])) {
            $methods = array_intersect($methods, (array) $options['only']);
        }

        if (isset($options['except'])) {
            $methods = array_diff($methods, (array) $options['except']);
        }

        return $methods;
    }
}
