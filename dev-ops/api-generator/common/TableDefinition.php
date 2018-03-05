<?php

class TableDefinition
{
    /** @var string*/
    public $tableName;

    /** @var string*/
    public $domainName;

    /** @var string */
    public $domainNameInPlural;

    /**
     * @var ColumnDefinition[]
     */
    public $columns;

    /**
     * @var Association[]
     */
    public $associations = [];

    /**
     * @var string
     */
    public $bundle;

    /**
     * @var bool
     */
    public $isMappingTable;

    public function hasDetail(): bool
    {
        /** @var Association $association */
        foreach ($this->associations as $association) {
            if ($association->writeOnly) {
                continue;
            }
            if ($association->inBasic === false) {
                return true;
            }
        }
        return false;
    }

    public function getPrimaryKeyColumns(): array
    {
        return array_filter($this->columns, function(ColumnDefinition $column) {
            return $column->isPrimaryKey;
        });
    }

    public function getForeignKeyColumns(): array
    {
        return array_filter($this->columns, function(ColumnDefinition $column) {
            return $column->isForeignKey;
        });
    }

    public function getNamespace(): string
    {
        return 'Shopware\\Api\\' . ucfirst($this->domainName);
    }

    public function getDefinitionClassName(): string
    {
        return ucfirst($this->domainName) . 'Definition';
    }

    public function getDefinitionClassFullName(): string
    {
        return $this->getNamespace() . '\\Definition\\' . $this->getDefinitionClassName();
    }

    public function isTranslationTable(): bool
    {
        return strpos($this->tableName, '_translation') !== false;
    }
}

