<?php

namespace Syspons\Sheetable\Models\Contracts;

use Syspons\Sheetable\Exports\DropdownSettings;

interface Dropdownable
{
//    /**
//     * e.g. [ 'country_id' => ['foreignModel' => 'Countrycode::class', 'foreignTitleColumn' => 'code'] ].
//     */

    /**
     * @return DropdownSettings[]
     */
    public static function getDropdownFields(): array;
}
