<?php

namespace Syspons\Sheetable\Exports;

class JoinConfig 
{
  /**
   * @param string[] $select The columns to select
   * @param JoinConfig[] $nested
   */
  public function __construct(
    private string $entity,
    private string $relation,
    private string $on,
    private array $select = [],
    private array $nested = [],
  ) {}

  public function __get($key): mixed 
  {
    return $this->$key ?: null;
  }
}