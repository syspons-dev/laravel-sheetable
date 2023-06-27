# Laravel-Sheetable 

A helper for importing and exporting Eloquent models.

Easily add import + export routes + controllers by adding a Trait to your model.

## Installation

```sh
$ composer require syspons/laravel-sheetable
```

## Usage

* Create your table and corresponding model, eg. with `php artisan make:model YourModel -m`
* Implement the `Sheetable` interface in your newly generated model.
* The package will register the following routes:
  * Export, *get*     `yourmodels/export` => export all desired entities
  * Template, *get*     `yourmodels/template` => export an empty spreadsheet
  * Import, *post*     `yourmodels/import` => import a spreadsheet

## Options

To change the default options use
```php
$ php artisan vendor:publish --provider="syspons\Sheetable\SheetableServiceProvider" --tag="config"
```
* Inherited from [laravel-targetable](https://docs.syspons-dev.com/laravel-targetable)
  * `namespace`: String or array with one ore multiple namespaces that should be monitored for the configured trait. Defaults to `App\Models`.
  * `namespace_mode`: Defines the search mode for the namespaces. `ClassFinder::STANDARD_MODE` will only find the exact matching namespace, `ClassFinder::RECURSIVE_MODE` will find all subnamespaces. Defaults to `ClassFinder::STANDARD_MODE`.
  * `prefix`: Defines the route prefix. Defaults to `api`.
* Formats
  * `export_format`: Defines the export format. Possible values are: `XLSX`, `CSV`, `TSV`, `ODS`, `XLS`, `HTML`, `MPDF`, `DOMPDF`, `TCPDF`. See the [Laravel-Excel Documentation](https://docs.laravel-excel.com/3.1/exports/export-formats.html). Defaults to `XLSX`.
  * `import_format`: Defines the import format. Possible values are: `XLSX`, `CSV`, `TSV`, `ODS`, `XLS`, `SLK`, `XML`, `GNUMERIC`, `HTML`. See the [Laravel-Excel Documentation](https://docs.laravel-excel.com/3.1/imports/import-formats.html). Defaults to `ANY`.

## Compatibility

Tested with Laravel 10.x.

## License

See [License File](license.md). Copyright © 2023 Jan Bladt.
