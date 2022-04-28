<?php

namespace Syspons\Sheetable\Exports;

use Illuminate\Database\Eloquent\Model;

class DropdownConfig
{
    /**
     * @var string name of the field in the main Table/sheet. Without _1,_2 suffix in case of n-m
     *             e.g. 'sdg_main_id' or 'sdg_additional_id'
     */
    private string $field;

    /** @var string DB foreign Model name containing the reference foreign id and text columns */
    private string $fkModel;

    /** @var Model Instance of the fkModel */
    private Model $fkModelInstance;

    /** @var string|null DB column name in the foreign reference Table, containing the descriptive text for this field */
    private ?string $fkTextCol = null;

    /** @var string|null DB column name in the foreign reference Table, containing the fk id */
    private ?string $fkIdCol = 'id';

    /** @var bool embedded dropdown values in formula field, do not add them to metadata sheet */
    private bool $embedded = false;

    /********************************************************************************
     * Many to many mapping table and field information ...
     ********************************************************************************/

    /** @var string|null the n-m-fields should appear next/right of this field; e.g. 'sdg_main_id' */
    private ?string $mappingRightOfField = null;

    private ?int $mappingMinFields = 0;


    /**
     * @var string[] fixed array of Dropdown options, currently not in use
     */
    private array $fixedList = [];

    /**
     * @return Model|string|null DB foreign Model name containing the reference foreign id and text columns
     */
    public function getFkModel(): Model|string|null
    {
        return $this->fkModel;
    }

    /**
     * @return $this
     */
    public function setFkModel(string $fkModel): self
    {
        $this->fkModel = $fkModel;
        $this->fkModelInstance = $fkModel::newModelInstance();

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

    public function getField(): string|null
    {
        return $this->field;
    }

    public function setField(string $field): self
    {
        $this->field = $field;

        return $this;
    }

    public function getFkIdCol(): string|null
    {
        return $this->fkIdCol;
    }

    /**
     * defaults to 'id'.
     *
     * @return $this
     */
    public function setFkIdCol(string $fkIdCol): self
    {
        $this->fkIdCol = $fkIdCol;

        return $this;
    }

    /********************************************************************************
     *
     * Many to many mapping table and field informations ...
     *
     ********************************************************************************/

    /**
     * @return string[]
     */
    public function getFixedList(): array
    {
        return $this->fixedList;
    }

    /**
     * @param string[] $fixedList
     */
    public function setFixedList(array $fixedList): self
    {
        $this->fixedList = $fixedList;

        return $this;
    }

    public function getMappingRightOfField(): ?string
    {
        return $this->mappingRightOfField;
    }

    public function setMappingRightOfField(?string $mappingRightOfField): self
    {
        $this->mappingRightOfField = $mappingRightOfField;

        return $this;
    }

    public function getMappingMinFields(): ?int
    {
        return $this->mappingMinFields;
    }

    public function setMappingMinFields(?int $mappingMinFields): self
    {
        $this->mappingMinFields = $mappingMinFields;

        return $this;
    }
}
