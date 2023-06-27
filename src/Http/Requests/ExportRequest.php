<?php

namespace Syspons\Sheetable\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Syspons\Sheetable\Services\SheetableService;

/**
 * Simple FormRequest Implementation for validation.
 */
class ExportRequest extends FormRequest
{
    private string $target;

    public function __construct(SheetableService $sheetableService)
    {
        $this->target = $sheetableService->getTarget();
    }

    /**
     * Get the validation rules that apply to the request.
     * 
     * @api
     */
    public function rules(): array
    {
        return [
          'ids' => 'nullable',
          'ids.*' => 'exists:'.$this->target::newModelInstance()->getTable().',id',
        ];
    }
}
