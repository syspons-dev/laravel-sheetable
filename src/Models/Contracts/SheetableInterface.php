<?php

namespace Syspons\Sheetable\Models\Contracts;

interface SheetableInterface
{
    public static function rules(mixed $id): array;
}
