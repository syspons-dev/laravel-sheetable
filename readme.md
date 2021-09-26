# Laravel-Sheetable - A helper for CRUD Routes in Laravel

Easily add a complete CRUD Route + Controller by adding a Trait to your model.

## Installation

```
$ composer require syspons/laravel-sheetable
```

## Usage

* Create your table and corresponding model, eg. with `php artisan make:model YourModel -m`
* Add the `Sheetable` Trait to your newly generated model.
* That's it. The package will register API CRUD routes (see [API Resource Routes](https://laravel.com/docs/8.x/controllers#api-resource-routes)) which will be handled by a generic SheetableController.

## Options

To change the default options use
```
$ php artisan vendor:publish --provider="syspons\Sheetable\SheetableServiceProvider" --tag="config"
```
* `middleware`: an array of middlewares that will be added to the generated routes
* `namespace`: string or array with one ore multiple namespaces that should be monitored for the Sheetable-Trait. Defaults to `App\Models`.
* `prefix`: route prefix. Defaults to `api`

## Compatibility

Tested with Laravel 8.x.

## License

See [License File](license.md). Copyright © 2021 Jan Bladt.
