<?php

namespace Syspons\Sheetable\Helpers;

use Illuminate\Support\Facades\Log;

class SheetableLog
{
    public static function log(string $message): void
    {
        Log::channel('sheetable')->info($message);
    }
}
