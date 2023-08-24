<?php

namespace Syspons\Sheetable\Helpers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Syspons\Sheetable\Services\SheetableService;

/**
 * Helper class for joins
 */
class SpreadsheetJoins
{
    public function __construct(
        private SheetableService $sheetableService,
    ) {}

    public function getMapping(Model $entity, array $headings): array
    {
        $ret = array_map(fn($heading) => $this->getNestedProperty($entity, $heading), $headings);
        return $ret;
    }

    public function getHeadings(string $entity, array $columns)
    {
        $ret = Arr::flatten($this->getJoinedColumns($entity::getJoins(), $columns));
        return $ret;
    }

    private function getJoinedColumns(array $joins, array $columns): array
    {
        foreach($joins as $join) {
            $columns = $join->getJoinedColumns($columns);
        }
        return $columns;
    }

    private function getNestedProperty(Model $entity, string $property): mixed
    {
        $nestedLevel = Str::substrCount($property, '.');
        if ($nestedLevel === 0) {
            return $entity->$property;
        }
        
        [$relation, $nestedProperty] = explode('.', $property, 2);
        if ($nestedLevel === 1) {
            switch(get_class($entity->$relation())) {
                case HasMany::class:
                {
                    $ret = $entity->$relation->pluck($nestedProperty)->join(', ');
                    return $ret;
                }
                default:
                return $entity->$relation->$nestedProperty;
              }
        }

        return $this->getNestedProperty($entity->$relation, $nestedProperty);
    }
}
