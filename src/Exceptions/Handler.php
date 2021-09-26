<?php

namespace Syspons\Sheetable\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * Render an exception into an HTTP response.
     *
     * @param Request $request
     *
     * @throws Throwable
     */
    public function render($request, Throwable $exception): Response
    {
        if ($exception instanceof ValidationException) {
            return response()->json(['errors' => $exception->errors()]);
        }

        return parent::render($request, $exception);
    }
}
