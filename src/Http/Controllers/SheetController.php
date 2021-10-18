<?php

namespace Syspons\Sheetable\Http\Controllers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Exception;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Syspons\Sheetable\Exports\SheetsExport;
use Syspons\Sheetable\Imports\SheetsImport;
use Syspons\Sheetable\Services\SheetableService;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class SheetController
{
    private SheetableService $sheetableService;

    public function __construct(SheetableService $sheetableService)
    {
        $this->sheetableService = $sheetableService;
    }

    /**
     * @throws Exception
     */
    public function export(): BinaryFileResponse
    {
        return Excel::download(
            new SheetsExport($this->getAllModels(), $this->getModel()),
            $this->getTableName().'.'.$this->sheetableService->getExportExtension()
        );
    }

    /**
     * Import TABLENAME.xlsx via upload.
     */
    public function import(Request $request): Redirector|Application|RedirectResponse
    {
        $import = new SheetsImport($this->getModel());
        $filePath = $request->file('file')->store(sys_get_temp_dir());
        Excel::import($import, $filePath);
        return redirect('/api/' . $this->getTableName())->with('success', 'Spreadsheet imported.');
    }

    public function getModel(): Model|string
    {
        return $this->sheetableService->getModelClassFromRequest();
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
