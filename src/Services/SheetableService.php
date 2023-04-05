<?php

namespace Syspons\Sheetable\Services;

use Syspons\Sheetable\Models\Contracts\Sheetable;
use berthott\Targetable\Services\TargetableService;
use berthott\Targetable\Enums\Mode;

class SheetableService extends TargetableService
{
    /**
     * The Constructor.
     */
    public function __construct()
    {
        parent::__construct(Sheetable::class, 'sheetable', Mode::Contract);
    }

    public function getExportExtension(): string
    {
        return strtolower(config('sheetable.export_format'));
    }
}
