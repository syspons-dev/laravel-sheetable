<?php

namespace Syspons\Sheetable\Exports;

use Closure;
use Facades\Syspons\Sheetable\Helpers\SpreadsheetUtils;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Arr;

class Join
{
  protected string $relationTableName;
  protected Model $parentEntity;
  protected Relation $relationObject;

  /**
   * @param string[] $select The columns to select
   * @param JoinConfig[] $nested
   */
  public function __construct(
    protected Model|string $parent,
    protected string $relation,
    protected array $select = [],
    protected array $nested = [],
  ) {
    $this->parentEntity = new $parent;
    $rel = $this->getRelated();
    $this->relationTableName = (new $rel)->getTable();
    $this->relationObject = $this->getRelationObject();
  }

  public function getRelated(): Model
  {
    $relation = $this->relation;
    $rel = $this->parentEntity->$relation();
    return $rel->getRelated();
  }

  public function getJoinedColumns($parentColumns): array
  {
    $columns = SpreadsheetUtils::getOrdinalColumnNames($this->relationTableName);
    if ($this->select) {
        $columns = array_intersect($columns, $this->select);
    }
    if ($this->nested && count($this->nested)) {
      $nested = $columns;
      foreach($this->nested as $n) {
        $nested = $n->getJoinedColumns($nested);
      }
      $columns = $nested;
    }
    $columns = $this->joinWithParentColumns($columns, $parentColumns);
    return $columns;
  }

  public function __get($key): mixed 
  {
    return $this->$key ?: null;
  }

  private function getRelationObject(): Relation
  {
    return ($this->parentEntity)->{$this->relation}();
  }

  private function joinWithParentColumns(array $columns, array $parentColumns): array
  {
    switch(get_class($this->relationObject)) {
      case BelongsTo::class:
      {
        $parentKey = $this->relationObject->getForeignKeyName();
        $columnIndex = array_search($parentKey, $parentColumns);
        $ret = array_replace($parentColumns, [$columnIndex => $this->array_map_recursive($columns, fn($column) => $this->relation.'.'.$column)]);
        return $ret;
      }
      case HasMany::class:
      {
        $parentKey = $this->relationObject->getForeignKeyName();
        array_splice($columns, array_search($parentKey, $columns), 1);
        $ret = array_merge($parentColumns, $this->array_map_recursive($columns, fn($column) => $this->relation.'.'.$column));
        return $ret;
      }
      default:
        return $parentColumns;
    }
  }

  private function array_map_recursive(array $array, Closure $cb): array
  {
    return Arr::map(
      $array,
      fn($item) => 
        is_array($item)
          ? $this->array_map_recursive($item, $cb)
          : $cb($item)
    );
  }
}
