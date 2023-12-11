# Laravel-Sheetable 

A helper for importing and exporting Eloquent models.

Easily add import + export routes + controllers by adding a Trait to your model.

This package utilizes [laravel-excel](https://github.com/SpartnerNL/Laravel-Excel) which is a wrapper around [PhpSpreadsheet](https://github.com/PHPOffice/phpspreadsheet/)

## Installation

```sh
$ composer require syspons/laravel-sheetable
```

## Concept

The package provides an interface to the database using excel import and export. It can be used to create or update entities, *not* to delete them.
For updating a unique identifier in the table is necessary.

### Dropdownable

Plain data from the table will be shown in the export as is. However, there might be relations within the data, that shall be shown inside the export (or import).
These are defined by `DropdownConfig`s. These represent a relation within the excel. It is possible to include **one to many** and **many to many** relationships.
For every relation you'll find a dropdown in your excel to select one of the related entities.
There will be a workbook inside your spreadsheet that contains all related models, mapped to their key, in order to make it possible to import these relations as well.

### Join

If you want more, than a single column of a relation joined to your export table, you will need to use the `Join` functionality. You have the possibility to add nested joins and to select any column of the joined table.

## Usage

* Create your table and corresponding model, eg. with `php artisan make:model YourModel -m`
* Implement the `Sheetable` interface in your newly generated model.
* The package will register the following routes:
  * Export, *get, post*     `yourmodels/export` => export all desired entities
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

## Architecture

* The package relies on [laravel-targetable](https://docs.syspons-dev.com/laravel-targetable) to connect specific functionality to Laravel model entities via contracts. (`Sheetable`, `Dropdownable`).
* The entrypoints to all functionality is provided by the `SheetController` by utilizing `laravel-excel`.
  * `SheetsExport` is used to provide export + template functionality.
  * `SheetImport` is used to provide import functionality.
  * Both rely on `SpreadsheetHelper` and `SpreadsheetUtils` for core functionality.
* `ExportRequest` implements the rules for selecting entities or columns to export
* The `SpreadsheetDropdowns` helper implements all functionality connected to relations / `Dropdownable`s.
* `SpreadsheetJoins` and `Join` gather the join functionality.


## Compatibility

Tested with Laravel 10.x.

## License

See [License File](license.md). Copyright © 2023 Jan Bladt.
