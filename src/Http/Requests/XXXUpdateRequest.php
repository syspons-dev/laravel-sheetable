<?php

//
//namespace Syspons\Sheetable\Http\Requests;
//
//use Illuminate\Database\Eloquent\Model;
//use Illuminate\Foundation\Http\FormRequest;
//use Illuminate\Support\Str;
//use Syspons\Sheetable\Models\Contracts\SheetableInterface;
//use Syspons\Sheetable\Models\Contracts\Targetable;
//use Syspons\Sheetable\Models\Traits\Targetable as TraitsTargetable;
//
//class UpdateRequest extends FormRequest implements SheetableInterface
//{
////    use TraitsTargetable;
//
//    private Model $instance;
//
//    /**
//     * Get the validation rules that apply to the request.
//     */
//    public function rules(): array
//    {
//        return array_merge(array_fill_keys($this->getInstance()->getFillable(), 'nullable'), $this->target::rules($this->getPrimaryId()));
//    }
//
//    protected function getInstance(): Model
//    {
//        if (!isset($this->instance)) {
//            $this->instance = new $this->target();
//        }
//
//        return $this->instance;
//    }
//
//    protected function getSingularName(): string
//    {
//        return Str::singular($this->getInstance()->getTable($this->target));
//    }
//
//    protected function isUpdate(): bool
//    {
//        return !empty($this->route($this->getSingularName()));
//    }
//
//    protected function getPrimaryId(): mixed
//    {
//        return $this->isUpdate() ? $this->route($this->getSingularName()) : null;
//    }
//}
