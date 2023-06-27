<?php

namespace Syspons\Sheetable\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Validators\ValidationException;
use PhpOffice\PhpSpreadsheet\Exception as PhpSpreadsheetException;

class ExcelImportValidationException extends Exception
{
    /**
     * Create a new exception instance.
     */
    public function __construct(private ValidationException|PhpSpreadsheetException|null $validationException = null)
    {
        parent::__construct('Validation Error.');
    }

    /**
     * Render the exception into an HTTP response.
     */
    public function render(/* Request $request */): JsonResponse
    {
        if ($this->validationException instanceof ValidationException) {
            $errors = $this->validationException->errors();
        } else {
            $errors = [$this->validationException->getMessage()];
        }

        return response()->json(['errors' => $errors], 422);
    }
}
