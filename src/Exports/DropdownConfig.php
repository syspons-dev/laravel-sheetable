<?php

namespace Syspons\Sheetable\Exports;

use Illuminate\Database\Eloquent\Model;

/**
 * Model holding information about a dropdownable column
 */
class DropdownConfig
{
    /**
     * Name of the field in the main table/sheet. 
     * 
     * Without _1,_2 suffix in case of n-m
     * e.g. 'sdg_main_id' or 'sdg_additional_id'
     */
    private string $field;

    /** 
     * Get field.
     */
    public function getField(): string|null
    {
        return $this->field;
    }

    /** 
     * Set field.
     */
    public function setField(string $field): self
    {
        $this->field = $field;

        return $this;
    }

    /** 
     * DB foreign Model name containing the reference foreign id and text columns.
     */
    private string $fkModel;

    /** 
     * Instance of the fkModel 
     */
    private Model $fkModelInstance;
    
    /** 
     * Get the foreign model.
     */
    public function getFkModel(): Model|string|null
    {
        return $this->fkModel;
    }

    /**
     * Set the foreign model.
     */
    public function setFkModel(string $fkModel): self
    {
        $this->fkModel = $fkModel;
        $this->fkModelInstance = $fkModel::newModelInstance();

        return $this;
    }

    /** 
     * Get foreign key.
     */
    public function getForeignKey(): string|null
    {
        return $this->fkModelInstance->getForeignKey();
    }

    /** 
     * DB column name in the foreign reference Table.
     * 
     * Containing the descriptive text for this field. 
     */
    private ?string $fkTextCol = null;

    /** 
     * Get the foreign column name.
     */
    public function getFkTextCol(): string
    {
        return $this->fkTextCol;
    }

    /** 
     * Get the foreign column name.
     */
    public function setFkTextCol(string $fkTextCol): self
    {
        $this->fkTextCol = $fkTextCol;

        return $this;
    }

    /** 
     * DB column name in the foreign reference Table.
     * 
     * Containing the fk id 
     */
    private ?string $fkIdCol = 'id';

    /** 
     * Get foreign id column.
     */
    public function getFkIdCol(): string|null
    {
        return $this->fkIdCol;
    }

    /** 
     * Set foreign id column.
     */
    public function setFkIdCol(string $fkIdCol): self
    {
        $this->fkIdCol = $fkIdCol;

        return $this;
    }

    /** 
     * Embedded dropdown values in formula field.
     * 
     * do not add them to metadata sheet
     */
    private bool $embedded = false;

    /** 
     * Get isEmbedded.
     */
    public function isEmbedded(): bool
    {
        return $this->embedded;
    }

    /** 
     * Set isEmbedded.
     */
    public function setEmbedded(bool $embedded): self
    {
        $this->embedded = $embedded;

        return $this;
    }

    /**
     * Fixed array of Dropdown options.
     * 
     * Currently not in use.
     * 
     * @var string[] 
     */
    private array $fixedList = [];

    /**
     * Get fixed list.
     * 
     * @return string[]
     */
    public function getFixedList(): array
    {
        return $this->fixedList;
    }

    /**
     * Set fixed list.
     * 
     * @param string[]
     */
    public function setFixedList(array $fixedList): self
    {
        $this->fixedList = $fixedList;

        return $this;
    }

    /** 
     * The n-m-fields should appear next/right of this field.
     * 
     * E.g. 'sdg_main_id' 
     */
    private ?string $mappingRightOfField = null;

    /**
     * Get mapping right of field.
     */
    public function getMappingRightOfField(): ?string
    {
        return $this->mappingRightOfField;
    }

    /**
     * Set mapping right of field.
     */
    public function setMappingRightOfField(?string $mappingRightOfField): self
    {
        $this->mappingRightOfField = $mappingRightOfField;

        return $this;
    }

    /** 
     * The minimum number of n-m-fields.
     */
    private ?int $mappingMinFields = 0;

    /**
     * Get mapping minimum fields.
     */
    public function getMappingMinFields(): ?int
    {
        return $this->mappingMinFields;
    }

    /**
     * Set mapping minimum fields.
     */
    public function setMappingMinFields(?int $mappingMinFields): self
    {
        $this->mappingMinFields = $mappingMinFields;

        return $this;
    }
}
