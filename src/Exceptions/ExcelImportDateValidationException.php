<?php

namespace Syspons\Sheetable\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class ExcelImportDateValidationException extends Exception
{
    private int $row;
    private string $attribute;

    /**
     * Create a new exception instance.
     */
    public function __construct(int $row, string $attribute)
    {
        parent::__construct('Validation Error.');
        $this->row = $row;
        $this->attribute = $attribute;
    }

    /**
     * Render the exception into an HTTP response.
     */
    public function render(/* Request $request */): JsonResponse
    {
        return response()->json(['errors' => [__('There was an error on row :row. :message', ['row' => $this->row, 'message' => __('validation.date', ['attribute' => $this->attribute])])]], 422);
    }
}
