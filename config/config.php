<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Route Middleware Configuration
    |--------------------------------------------------------------------------
    |
    | Configurations for the route.
    |
    */

    'middleware' => ['api'],

    /*
    |--------------------------------------------------------------------------
    | Model Namespace Configuration
    |--------------------------------------------------------------------------
    |
    | Defines one or multiple model namespaces.
    |
    | e.g. 'namespace' => 'App\Models\Sheetable',
    |
    */

    'namespace' => 'App\Models',

    /*
    |--------------------------------------------------------------------------
    | API Prefix
    |--------------------------------------------------------------------------
    |
    | Defines the api prefix.
    |
    */

    'prefix' => 'api',


    /*
    |--------------------------------------------------------------------------
    | export_format
    |--------------------------------------------------------------------------
    |
    | Defines the export format. Possible values are:
    | 'XLSX', 'CSV', 'TSV', 'ODS', 'XLS', 'HTML', 'MPDF', 'DOMPDF', 'TCPDF'
    | See https://docs.laravel-excel.com/3.1/exports/export-formats.html
    |
    */
    'export_format' => 'XLSX',

    /*
    |--------------------------------------------------------------------------
    | import_format
    |--------------------------------------------------------------------------
    |
    | Defines the import format. Possible values are:
    | 'XLSX', 'CSV', 'TSV', 'ODS', 'XLS', 'HTML', 'MPDF', 'DOMPDF', 'TCPDF'
    | See https://docs.laravel-excel.com/3.1/exports/export-formats.html
    |
    */
    'import_format' => 'ANY'

];
