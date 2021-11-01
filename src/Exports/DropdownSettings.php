<?php

namespace Syspons\Sheetable\Exports;

class DropdownSettings
{
    private string $field;
    private string $foreignModel;
    private string $foreignTitleColumn;
    private bool $embeddedValues = false;

    public function getForeignModel(): string
    {
        return $this->foreignModel;
    }

    public function setForeignModel(string $foreignModel): self
    {
        $this->foreignModel = $foreignModel;

        return $this;
    }

    public function getForeignTitleColumn(): string
    {
        return $this->foreignTitleColumn;
    }

    public function setForeignTitleColumn(string $foreignTitleColumn): self
    {
        $this->foreignTitleColumn = $foreignTitleColumn;

        return $this;
    }

    public function isEmbeddedValues(): bool
    {
        return $this->embeddedValues;
    }

    public function setEmbeddedValues(bool $embeddedValues): self
    {
        $this->embeddedValues = $embeddedValues;

        return $this;
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function setField(string $field): self
    {
        $this->field = $field;

        return $this;
    }
}
