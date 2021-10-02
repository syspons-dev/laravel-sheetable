<?php

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

        // add routes
        Route::group($this->routeConfiguration(), function () use ($sheetableService) {
            foreach ($sheetableService->getSheetableClasses() as $sheetableClass) {
                $tableName = $sheetableClass::newModelInstance()->getTable();

                Route::get(
                    '/export/'.$tableName,
                    [SheetController::class, 'export']
                )->name('export.'.$tableName);

                Route::post(
                    '/import/'.$tableName,
                    [SheetController::class, 'import']
                )->name('import.'.$tableName);
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
