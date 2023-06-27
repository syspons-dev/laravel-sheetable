<?php

namespace Syspons\Sheetable\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * Render an exception into an HTTP response.
     * 
     * Wrap `ValidationException`s into errors array and return a 422 Http Response.
     * This 422 response will be interpreted by the frontend and shown to the user.
     * 
     * @link \TODO
     *
     * @param \Illuminate\Http\Request $request
     * @param \Throwable               $exception
     *
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws Throwable
     */
    public function render($request, Throwable $exception)
    {
        if ($exception instanceof ValidationException) {
            return response()->json(['errors' => $exception->errors()], 422);
        }

        return parent::render($request, $exception);
    }
}
