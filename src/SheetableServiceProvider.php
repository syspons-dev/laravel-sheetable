<?php

/** @noinspection PhpPossiblePolymorphicInvocationInspection */

namespace Syspons\Sheetable;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Route;
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
//        $this->app->singleton(ExceptionHandler::class, Handler::class);

        // add config
        $this->mergeConfigFrom(__DIR__.'/../config/sheetable.php', 'sheetable');
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

        // add routes
        Route::group($this->routeConfiguration(), function () use ($sheetableService) {
            foreach ($sheetableService->getSheetableClasses() as $sheetableClass) {
                $tableName = $sheetableClass::newModelInstance()->getTable();

                Route::get(
                    $tableName.'/export/',
                    [SheetController::class, 'export']
                )->name($tableName.'.export');

                Route::post(
                    $tableName.'/import/',
                    [SheetController::class, 'import']
                )->name($tableName.'.import');
            }
        });
    }

    #[ArrayShape(['middleware' => 'mixed', 'prefix' => 'mixed'])]
    protected function routeConfiguration(): array
    {
        return [
            'middleware' => config('sheetable.middleware'),
            'prefix' => config('sheetable.prefix'),
        ];
    }
}
