<?php

namespace Syspons\Sheetable\Models\Contracts;

interface Dropdownable
{




    /**
     * e.g. [ 'country_id' => ['foreignModel' => 'Countrycode::class', 'foreignTitleColumn' => 'code'] ]
     * @return array
     */
    public static function getDropdownFields(): array;

}
