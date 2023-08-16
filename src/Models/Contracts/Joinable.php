<?php

namespace Syspons\Sheetable\Models\Contracts;

use Syspons\Sheetable\Exports\JoinConfig;

/**
 * Interface to add the joinable functionality.
 */
interface Joinable
{
    /**
     * An array of join configs for the joinables.
     * 
     * **required**
     * 
     * @api
     * @return JoinConfig[]
     */
    public static function getJoins(): array;
}
