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
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class SheetController
{
    private SheetableService $sheetableService;
    private SpreadsheetHelper $spreadsheetHelper;

    public function __construct(SheetableService $sheetableService, SpreadsheetHelper $spreadsheetHelper)
    {
        $this->sheetableService = $sheetableService;
        $this->spreadsheetHelper = $spreadsheetHelper;
    }

    /**
     * @throws Exception
     */
    public function export(ExportRequest $request): BinaryFileResponse
    {
        return Excel::download(
            new SheetsExport(Scopeable::filterScopes($this->getExportModels($request->input('ids', []))), $this->getModel(), $this->spreadsheetHelper),
            $this->getTableName().'.'.$this->sheetableService->getExportExtension()
        );
    }

    /**
     * @throws Exception
     */
    public function template(): BinaryFileResponse
    {
        return Excel::download(
            new SheetsExport($this->getAllModels(), $this->getModel(), $this->spreadsheetHelper, true),
            $this->getTableName().'.'.$this->sheetableService->getExportExtension()
        );
    }

    /**
     * Import TABLENAME.xlsx via upload.
     *
     * @throws ExcelImportValidationException
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

        return $this->getAllModels()->toArray();
    }

    public function getModel(): Model|string
    {
        return $this->sheetableService->getModelClassFromRequest();
    }

    private function getExportModels(array $ids = []): Collection
    {
        if (!$ids || empty($ids)) {
            return $this->getModel()::all();
        }
        return $this->getModel()::whereIn('id', $ids)->get();
    }

    private function getAllModels(): Collection
    {
        //User::all()
        return $this->getModel()::all();
    }

    private function getTableName(): string
    {
        return $this->getModel()::newModelInstance()->getTable();
    }
}
