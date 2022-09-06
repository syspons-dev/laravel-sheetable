<?php

namespace Syspons\Sheetable\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class ExcelImportScopeableException extends Exception
{
    private int $row;

    /**
     * Create a new exception instance.
     */
    public function __construct(int $row)
    {
        parent::__construct('Scope not allowed.');
        $this->row = $row;
    }

    /**
     * Render the exception into an HTTP response.
     */
    public function render(/* Request $request */): JsonResponse
    {
        return response()->json(['errors' => [__('There was an error on row :row. :message', ['row' => $this->row, 'message' => __('Unauthorized.')])]], 422);
    }
}
