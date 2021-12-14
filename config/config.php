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
    | Defines the export format.
    | Possible values:
    | 'XLSX', 'CSV', 'TSV', 'ODS', 'XLS', 'HTML', 'MPDF', 'DOMPDF', 'TCPDF'
    |
    */
    'export_format' => 'XLSX',

    /*
    |--------------------------------------------------------------------------
    | import_format
    |--------------------------------------------------------------------------
    |
    | Defines the export format.
    | Possible values:
    | 'XLSX', 'CSV', 'TSV', 'ODS', 'XLS', 'HTML', 'MPDF', 'DOMPDF', 'TCPDF'
    | and 'ANY' which means any of the values above may be imported
    |
    */
    'import_format' => 'ANY'

];
