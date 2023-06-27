<?php

namespace Syspons\Sheetable\Models\Contracts;

use Syspons\Sheetable\Exports\DropdownConfig;

/**
 * Interface to add the dropdownable functionality.
 */
interface Dropdownable
{
    /**
     * An array of dropdown configs for the dropdownable fields.
     * 
     * **required**
     * 
     * @api
     * @return DropdownConfig[]
     */
    public static function getDropdownFields(): array;
}
