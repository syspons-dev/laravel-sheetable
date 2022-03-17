<?php

namespace Syspons\Sheetable\Tests\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use JetBrains\PhpStorm\ArrayShape;
use Syspons\Sheetable\Models\Contracts\Sheetable;

class SimpleDummy extends Model implements Sheetable
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'firstname',
        'lastname'
    ];

    /**
     * @param mixed|null $id
     * @return array
     */
    #[ArrayShape(['firstname' => "string"])]
    public static function importRules(): array
    {
        return [
            'firstname' => 'required',
        ];
    }

    protected static function newFactory(): SimpleDummyFactory
    {
        return SimpleDummyFactory::new();
    }
}
