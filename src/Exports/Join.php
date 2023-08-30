<?php

namespace Syspons\Sheetable\Exports;

use Closure;
use Facades\Syspons\Sheetable\Helpers\SpreadsheetUtils;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Arr;

/**
 * Configuration and helper class for export joins.
 */
class Join
{
  protected string $relationTableName;
  protected Model $parentEntity;
  protected Relation $relationObject;

  /**
   * @param Model|string $parent The parent class
   * @param string $relation The relation name
   * @param string[] $select The columns to select
   * @param string[] $except The columns to except
   * @param JoinConfig[] $nested
   */
  public function __construct(
    protected Model|string $parent,
    protected string $relation,
    protected array $nested = [],
    protected array|null $select = null,
    protected array|null $except = null,
  ) {
    $this->parentEntity = new $parent;
    $rel = $this->getRelated();
    $this->relationTableName = (new $rel)->getTable();
    $this->relationObject = $this->getRelationObject();
  }

  /**
   * Get the related class
   */
  public function getRelated(): Model
  {
    $relation = $this->relation;
    $rel = $this->parentEntity->$relation();
    return $rel->getRelated();
  }

  /**
   * Get the columns after the join
   */
  public function getJoinedColumns($parentColumns): array
  {
    $columns = SpreadsheetUtils::getOrdinalColumnNames($this->relationTableName);
    $columns = $this->applySelected($columns);
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

  /**
   * Get the protected members
   */
  public function __get($key): mixed 
  {
    return $this->$key ?: null;
  }

  /**
   * Instantiate the relation object
   */
  private function getRelationObject(): Relation
  {
    return ($this->parentEntity)->{$this->relation}();
  }

  /**
   * Remove the join column from the parent columns and add the joined ones instead.
   * 
   * * `HasOne` and `BelongsTo` will use insert the joined columns where the reference column was
   * * `HasMany`and `BelongsToMany`will add the joined columns at the end. 
   */
  private function joinWithParentColumns(array $columns, array $parentColumns): array
  {
    $class = get_class($this->relationObject);
    switch($class) {
      case HasOne::class:
      {
        $parentKey = $this->relationObject->getLocalKeyName();
        $columnIndex = array_search($parentKey, $parentColumns);
        $ret = array_replace($parentColumns, [$columnIndex => $this->array_map_recursive($columns, fn($column) => $this->relation.'.'.$column)]);
        return $ret;
      }
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
        $columns = Arr::where($columns, fn($column) => $column !== $parentKey);
        $ret = array_merge($parentColumns, $this->array_map_recursive($columns, fn($column) => $this->relation.'.'.$column));
        return $ret;
      }
      case BelongsToMany::class:
      {
        $ret = array_merge($parentColumns, $this->array_map_recursive($columns, fn($column) => $this->relation.'.'.$column));
        return $ret;
      }
      default:
        return $parentColumns;
    }
  }

  /**
   * Return the column that is joined on
   */
  private function joinOn(): array
  {
    switch(get_class($this->relationObject)) {
      case HasOne::class:
        return [$this->relationObject->getLocalKeyName()];
      case BelongsTo::class:
      case HasMany::class:
        return [$this->relationObject->getForeignKeyName()];
      default:
        return [];
    }
  }

  /**
   * Apply a callback recursively on an arrays items
   */
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

  /**
   * Apply select and except properties.
   */
  private function applySelected(array $columns): array
  {
    if ($this->select !== null) {
      $columns = array_intersect($columns, array_merge($this->select, $this->joinOn()));
    }

    if ($this->except !== null) {
      $columns = array_diff($columns, $this->except);
    }

    return $columns;
  }
}
