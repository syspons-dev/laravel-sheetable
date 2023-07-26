<?php

namespace Syspons\Sheetable\Http\Controllers;

use berthott\Scopeable\Facades\Scopeable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Validators\ValidationException;
use PhpOffice\PhpSpreadsheet\Exception;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Syspons\Sheetable\Exceptions\ExcelImportValidationException;
use Syspons\Sheetable\Exports\SheetsExport;
use Syspons\Sheetable\Helpers\SpreadsheetHelper;
use Syspons\Sheetable\Http\Requests\ExportRequest;
use Syspons\Sheetable\Imports\SheetsImport;
use Syspons\Sheetable\Services\SheetableService;

/**
 * Sheetable API endpoint implementation.
 */
class SheetController
{
    public function __construct(
        private SheetableService $sheetableService,
        private SpreadsheetHelper $spreadsheetHelper
    ) {}

    /**
     * Export the requested ids with the selected columns.
     * 
     * @throws Exception
     * @api
     */
    public function export(ExportRequest $request): BinaryFileResponse
    {
        return Excel::download(
            new SheetsExport(
                Scopeable::filterScopes($this->getEntities($request->input('ids', []))), 
                $this->getModel(), 
                $this->spreadsheetHelper,
                select: $request->input('select', []),
            ),
            $this->getTableName().'.'.$this->sheetableService->getExportExtension()
        );
    }

    /**
     * Export a template.
     * 
     * @throws Exception
     * @api
     */
    public function template(): BinaryFileResponse
    {
        return Excel::download(
            new SheetsExport(
                $this->getEntities(), 
                $this->getModel(), 
                $this->spreadsheetHelper, 
                isTemplate: true
            ),
            $this->getTableName().'.'.$this->sheetableService->getExportExtension()
        );
    }

    /**
     * Import TABLENAME.xlsx via upload.
     *
     * @throws ExcelImportValidationException
     * @api
     */
    public function import(Request $request): array
    {
        $import = new SheetsImport($this->getModel(), $this->spreadsheetHelper);
        $filePath = $request->file('file')->store(sys_get_temp_dir());

        try {
            Excel::import($import, $filePath);
        } catch (ValidationException $e) {
            throw new ExcelImportValidationException($e);
        }

        return $this->getEntities()->toArray();
    }

    /**
     * The model.
     */
    private function getModel(): Model|string
    {
        return $this->sheetableService->getTarget();
    }
    
    /**
     * The table name.
     */
    private function getTableName(): string
    {
        return $this->getModel()::newModelInstance()->getTable();
    }

    /**
     * Get all or a subset of entities.
     */
    private function getEntities(array $ids = []): Collection
    {
        if (!$ids || empty($ids)) {
            return $this->getModel()::all();
        }
        return $this->getModel()::whereIn('id', $ids)->get();
    }
}
