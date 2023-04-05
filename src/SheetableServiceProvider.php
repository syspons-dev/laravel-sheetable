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

                Route::match(
                    ['get', 'post'],
                    '/export/'.$tableName,
                    [SheetController::class, 'export']
                )->name($tableName.'.export');
                Route::get(
                    '/template/'.$tableName,
                    [SheetController::class, 'template']
                )->name($tableName.'.template');
                Route::post(
                    '/import/'.$tableName,
                    [SheetController::class, 'import']
                )->name($tableName.'.import');
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
}
