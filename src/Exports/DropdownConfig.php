<?php

namespace Syspons\Sheetable\Exports;

use Illuminate\Database\Eloquent\Model;

class DropdownConfig
{
    /** @var string name of the field in the main Table/sheet */
    private string $field;

    /** @var string DB foreign Model name containing the reference foreign id and text columns */
    private string $fkModel;

    /** @var string DB column name in the foreign reference Table, containing the descriptive text for this field */
    private string $fkTextCol;

    /** @var string DB column name in the foreign reference Table, containing the fk id */
    private string $fkIdCol = 'id';

    /** @var bool embedded dropdown values in formula field, do not add them to metadata sheet */
    private bool $embedded = false;

    /**
     * @return Model|string DB foreign Model name containing the reference foreign id and text columns
     */
    public function getFkModel(): Model|string
    {
        return $this->fkModel;
    }

    /**
     * @return $this
     */
    public function setFkModel(string $fkModel): self
    {
        $this->fkModel = $fkModel;

        return $this;
    }

    /**
     * @return string DB column name in the foreign reference Table, containing the descriptive text for this field
     */
    public function getFkTextCol(): string
    {
        return $this->fkTextCol;
    }

    public function setFkTextCol(string $fkTextCol): self
    {
        $this->fkTextCol = $fkTextCol;

        return $this;
    }

    public function isEmbedded(): bool
    {
        return $this->embedded;
    }

    public function setEmbedded(bool $embedded): self
    {
        $this->embedded = $embedded;

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

    public function getFkIdCol(): string
    {
        return $this->fkIdCol;
    }
}
