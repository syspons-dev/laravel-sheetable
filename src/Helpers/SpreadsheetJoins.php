<?php

namespace Syspons\Sheetable\Helpers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Syspons\Sheetable\Services\SheetableService;

/**
 * Helper class for joins
 */
class SpreadsheetJoins
{
    public function __construct(
        private SheetableService $sheetableService,
        private SpreadsheetUtils $utils, 
    ) {}

    /**
     * Map the data
     */
    public function getMapping(Model $entity, array $headings): array
    {
        $ret = array_map(fn($heading) => $this->utils->getNestedProperty($entity, $heading), $headings);
        return $ret;
    }

    /**
     * Build the columns
     */
    public function getHeadings(string $entity, array $columns)
    {
        $ret = Arr::flatten($this->getJoinedColumns($entity::getJoins(), $columns));
        return $ret;
    }

    /**
     * Apply all joins
     */
    private function getJoinedColumns(array $joins, array $columns): array
    {
        foreach($joins as $join) {
            $columns = $join->getJoinedColumns($columns);
        }
        return $columns;
    }
}
