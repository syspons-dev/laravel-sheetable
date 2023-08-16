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
        $ret = Arr::dot(array_replace($entity->toArray(), $this->getJoin($entity)));
        return $ret;
    }

    public function getHeadings(string $entity, array $columns)
    {
        $joins = $this->getJoinKeys(new $entity);
        $ret = Arr::flatten(array_map(fn ($column) => array_key_exists($column, $joins) ? $joins[$column] : $column, $columns));
        return $ret;
    }

    private function getJoin(Model $entity): array
    {
        return collect($entity->getJoins())->mapWithKeys(function ($join) use ($entity) {
            $relation = $join->relation; 
            $data = $join->select
                ? $entity->$relation->only($join->select)
                : $entity->$relation->toArray();
            return [$join->on => $data];
        })->toArray();
    }

    private function getJoinKeys(Model $entity): array
    {
        return collect($entity->getJoins())->mapWithKeys(function ($join) {
            $tableName = (new $join->entity)->getTable();
            $columns = SpreadsheetUtils::getOrdinalColumnNames($tableName);
            if ($join->select) {
                $columns = array_intersect($columns, $join->select);
            }
            return [$join->on => array_map(fn ($column) => "$tableName.$column", $columns)];
        })->toArray();
    }
}
