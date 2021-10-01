<?php

namespace Syspons\Sheetable\Tests;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use JetBrains\PhpStorm\ArrayShape;
use Syspons\Sheetable\Models\Contracts\Sheetable;

class User extends Model implements Sheetable
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'firstname',
        'lastname',
    ];

    /**
     * @param mixed|null $id
     * @return array
     */
    #[ArrayShape(['firstname' => "string"])]
    public static function rules(): array {
        return [
            'firstname' => 'required',
        ];
    }

    protected static function newFactory(): UserFactory
    {

        return UserFactory::new();
    }
}
