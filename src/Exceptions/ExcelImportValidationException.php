<?php

namespace Syspons\Sheetable\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Validators\ValidationException;
use PhpOffice\PhpSpreadsheet\Exception as PhpSpreadsheetException;

class ExcelImportValidationException extends Exception
{
    /**
     * The recommended response to send to the client.
     */
    private ValidationException|PhpSpreadsheetException $validationException;

    /**
     * Create a new exception instance.
     */
    public function __construct(ValidationException|PhpSpreadsheetException|null $validationException = null)
    {
        parent::__construct('Validation Error.');
        $this->validationException = $validationException;
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
