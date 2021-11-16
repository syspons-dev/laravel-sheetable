<?php

namespace Syspons\Sheetable\Http\Controllers;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Exception;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Syspons\Sheetable\Exports\SheetsExport;
use Syspons\Sheetable\Imports\SheetsImport;
use Syspons\Sheetable\Services\SheetableService;
use Syspons\Sheetable\Services\SpreadsheetHelper;

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
    public function export(): BinaryFileResponse
    {
        return Excel::download(
            new SheetsExport($this->getAllModels(), $this->getModel(), $this->spreadsheetHelper),
            $this->getTableName().'.'.$this->sheetableService->getExportExtension()
        );
    }

    /**
     * Import TABLENAME.xlsx via upload.
     */
    public function import(Request $request): array
    {
        $import = new SheetsImport($this->getModel(), $this->spreadsheetHelper);
        $filePath = $request->file('file')->store(sys_get_temp_dir());

        Excel::import($import, $filePath);

        // return redirect(env('APP_URL').'/api/'.$this->getTableName())->with('success', 'Spreadsheet imported.');
        // TODO return a JSON with more info or redirect to list of entities?
        return ['message' => 'ok'];
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
