<?php

namespace Syspons\Sheetable\Exports;

class JoinConfig 
{
  public function __construct(
    private string $entity,
    private string $relation,
    private string $on,
    private array $select = [],
  ) {}

  public function __get($key): mixed 
  {
    return $this->$key ?: null;
  }
}