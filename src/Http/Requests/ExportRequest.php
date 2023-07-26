<?php

namespace Syspons\Sheetable\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Syspons\Sheetable\Helpers\SpreadsheetHelper;
use Syspons\Sheetable\Services\SheetableService;

/**
 * Simple FormRequest Implementation for validation.
 */
class ExportRequest extends FormRequest
{
    private string $target;

    public function __construct(
        SheetableService $sheetableService,
        private SpreadsheetHelper $helper
    )
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
            'select' => 'nullable|array',
            'select.*' => Rule::in($this->helper->acceptedColumns($this->target)),
        ];
    }
}
