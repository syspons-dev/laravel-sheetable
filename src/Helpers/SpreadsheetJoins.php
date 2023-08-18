<?php

namespace Syspons\Sheetable\Helpers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Syspons\Sheetable\Services\SheetableService;
use Facades\Syspons\Sheetable\Helpers\SpreadsheetUtils;

/**
 * Helper class for joins
 */
class SpreadsheetJoins
{
    public function __construct(
        private SheetableService $sheetableService,
    ) {}

    public function getMapping(Model $entity): array
    {
        $ret = Arr::dot(array_replace($entity->toArray(), $this->getJoinData($entity, $entity::getJoins())));
        return $ret;
    }

    public function getHeadings(string $entity, array $columns)
    {
        $joins = $this->getJoinKeys($entity::getJoins());
        $mapped = array_map(fn ($column) => array_key_exists($column, $joins) ? $joins[$column] : $column, $columns);
        $ret = Arr::flatten($mapped);
        return $ret;
    }

    private function getJoinData(Model $entity, array $joins): array
    {
        return collect($joins)->mapWithKeys(function ($join) use ($entity) {
            $relation = $join->relation; 
            $data = $join->select
                ? $entity->$relation->only($join->select)
                : $entity->$relation->toArray();
            
            if ($join->nested && count($join->nested)) {
                $data = array_replace($data, $this->getJoinData($entity->$relation, $join->nested));
            }
            return [$join->on => $data];
        })->toArray();
    }

    private function getJoinKeys(array $joins): array
    {
        return collect($joins)->mapWithKeys(function ($join) {
            $tableName = (new $join->entity)->getTable();
            $columns = SpreadsheetUtils::getOrdinalColumnNames($tableName);
            if ($join->select) {
                $columns = array_intersect($columns, $join->select);
            }
            if ($join->nested && count($join->nested)) {
                $nested = $this->getJoinKeys($join->nested);
            }
            $joined = array_map(
                fn($column) => isset($nested) && array_key_exists($column, $nested)
                    ? array_map(fn($n) => "$tableName.$n", $nested[$column])
                    : "$tableName.$column"
                , $columns);
            return [$join->on => $joined];
        })->toArray();
    }
}
