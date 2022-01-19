<?php

namespace Syspons\Sheetable\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Validators\ValidationException;

class ExcelImportValidationException extends Exception
{
    /**
     * The recommended response to send to the client.
     */
    private ValidationException $validationException;

    /**
     * Create a new exception instance.
     */
    public function __construct(ValidationException|null $validationException = null)
    {
        parent::__construct('Validation Error.');
        $this->validationException = $validationException;
    }

    /**
     * Render the exception into an HTTP response.
     */
    public function render(/* Request $request */): JsonResponse
    {
        return response()->json(['errors' => $this->validationException->errors()], 422);
    }
}
