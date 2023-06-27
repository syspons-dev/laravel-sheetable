<?php

namespace Syspons\Sheetable\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class ExcelImportDateValidationException extends Exception
{
    /**
     * Create a new exception instance.
     */
    public function __construct(
        private int $row, 
        private string $attribute)
    {
        parent::__construct('Validation Error.');
    }

    /**
     * Render the exception into an HTTP response.
     */
    public function render(/* Request $request */): JsonResponse
    {
        return response()->json(['errors' => [__('There was an error on row :row. :message', ['row' => $this->row, 'message' => __('validation.date', ['attribute' => $this->attribute])])]], 422);
    }
}
