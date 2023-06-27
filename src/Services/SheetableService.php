<?php

namespace Syspons\Sheetable\Services;

use Syspons\Sheetable\Models\Contracts\Sheetable;
use berthott\Targetable\Services\TargetableService;
use berthott\Targetable\Enums\Mode;

/**
 * TargetableService implementation for a sheetable class.
 * 
 * @link https://docs.syspons-dev.com/laravel-targetable
 */
class SheetableService extends TargetableService
{
    public function __construct()
    {
        parent::__construct(Sheetable::class, 'sheetable', Mode::Contract);
    }

    /**
     * Get the export extension.
     */
    public function getExportExtension(): string
    {
        return strtolower(config('sheetable.export_format'));
    }
}
