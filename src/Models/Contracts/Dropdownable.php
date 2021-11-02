<?php

namespace Syspons\Sheetable\Models\Contracts;

use Syspons\Sheetable\Exports\DropdownConfig;

interface Dropdownable
{
    /**
     * @return DropdownConfig[]
     */
    public static function getDropdownFields(): array;
}
