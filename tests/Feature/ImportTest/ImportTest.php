<?php

namespace Syspons\Sheetable\Tests\Feature\ImportTest;

use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use Syspons\Sheetable\Tests\TestCase;

/**
 *
 */
class ImportTest extends TestCase
{
    public function test_import_simple_dummies(): void
    {
        Excel::fake();

        $file = new UploadedFile(
            __DIR__ . '/simple-dummies-upload.xlsx',
            'simple-dummies-upload.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $response = $this->postJson('/api/import/simple_dummies', [
            'file' => $file
        ]);

        $this->assertEquals(200, $response->getStatusCode());

        // TODO AJ does not work
//        Excel::assertImported('simpleDummys-upload.xlsx');
//        $this->assertDatabaseCount('simpleDummys', 10);
//        $this->assertDatabaseHas(
//            'simpleDummys',
//            ['firstname' => 'Rick', 'lastname' => 'Sanchez']
//        );
    }
}
