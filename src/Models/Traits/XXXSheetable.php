<?php

//
///** @noinspection PhpDynamicAsStaticMethodCallInspection */
//
//namespace Syspons\Sheetable\Models\Traits;
//
//use Illuminate\Database\Eloquent\Model;
//use Syspons\Sheetable\Facades\SheetableServiceFacade;
//use Syspons\Sheetable\Models\Contracts\SheetableInterface;
//
//trait Sheetable
//{
//    public static function rules(/* mixed $id */): array
//    {
//        return [];
//    }
//
//    /**
//     * The target model.
//     */
//    private string|SheetableInterface|Model $target;
//
//    public function initTarget(): void
//    {
//        $this->target = SheetableServiceFacade::getModelClassFromRequest();
//    }
//
//    public function getExportExtension(): string
//    {
//        return strtolower(config('sheetable.export_format'));
//    }
//}
