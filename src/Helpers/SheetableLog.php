<?php

namespace Syspons\Sheetable\Helpers;

use Illuminate\Support\Facades\Log;

/**
 * Logging helper class.
 */
class SheetableLog
{
    /**
     * Log a message to the `sheetable` log.
     */
    public static function log(string $message): void
    {
        Log::channel('sheetable')->info($message);
    }
}
