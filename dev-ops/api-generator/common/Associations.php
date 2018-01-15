<?php

abstract class Association
{
    /**
     * @var string
     */
    public $sourceTable;

    /**
     * @var string
     */
    public $sourceColumn;

    /**
     * @var string
     */
    public $referenceTable;

    /**
     * @var string
     */
    public $referenceColumn;

    /**
     * @var bool
     */
    public $inBasic;

    /**
     * @var string
     */
    public $property;

    /**
     * @var array
     */
    public $joinCondition;

    /**
     * @var string
     */
    public $propertyPlural;

    /**
     * @var bool
     */
    public $nullable = false;

    /**
     * @var string
     */
    public $cascadeDelete;

    public $writeOnly = false;

    public function __construct(
        string $sourceTable,
        string $referenceTable,
        string $property,
        ?string $sourceColumn,
        ?string $referenceColumn,
        bool $inBasic = false,
        bool $nullable = false,
        array $joinCondition = [],
        ?string $cascadeDelete = null,
        bool $writeOnly = false
    ) {
        $this->sourceTable = $sourceTable;
        $this->sourceColumn = $sourceColumn;
        $this->referenceTable = $referenceTable;
        $this->referenceColumn = $referenceColumn;
        $this->inBasic = $inBasic;
        $this->property = $property;
        $this->joinCondition = $joinCondition;

        $this->propertyPlural = Util::buildDomainPlural($property);
        $this->referenceBundle = Util::getBundleName($referenceTable);
        $this->referenceTableDomainName = Util::getTableDomainName($this->referenceTable);
        $this->nullable = $nullable;
        $this->cascadeDelete = $cascadeDelete;
        $this->writeOnly = $writeOnly;
    }
}

class OneToOneAssociation extends Association
{
    public function __construct(
        string $sourceTable,
        string $referenceTable,
        string $property,
        bool $inBasic = false,
        ?string $sourceColumn = null,
        ?string $referenceColumn = null,
        bool $nullable = false,
        array $joinCondition = [],
        bool $writeOnly = false
    ) {
        parent::__construct($sourceTable, $referenceTable, $property, $sourceColumn, $referenceColumn, $inBasic, $nullable, $joinCondition, 'CASCADE', $writeOnly);

        if ($this->referenceColumn === null) {
            $this->referenceColumn = $this->sourceTable . '_id';
        }
        if ($this->sourceColumn === null) {
            $this->sourceColumn = 'id';
        }
    }
}

class OneToManyAssociation extends Association
{
    public function __construct(
        string $sourceTable,
        string $referenceTable,
        string $property,
        bool $inBasic = false,
        ?string $sourceColumn = null,
        ?string $referenceColumn = null,
        bool $nullable = false,
        array $joinCondition = [],
        $onDelete,
        bool $writeOnly = false
    ) {
        parent::__construct($sourceTable, $referenceTable, $property, $sourceColumn, $referenceColumn, $inBasic, $nullable, $joinCondition, $onDelete, $writeOnly);
        if ($this->referenceColumn === null) {
            $this->referenceColumn = $this->sourceTable . '_id';
        }
        if ($this->sourceColumn === null) {
            $this->sourceColumn = 'id';
        }

    }
}

class ManyToManyAssociation extends Association
{
    public $mappingTable;

    public $mappingSourceColumn;

    public $mappingReferenceColumn;

    public function __construct(
        string $sourceTable,
        string $referenceTable,
        string $property,
        string $mappingTable,
        bool $inBasic = false,
        ?string $mappingSourceColumn = null,
        ?string $mappingReferenceColumn = null,
        ?string $sourceColumn = null,
        ?string $referenceColumn = null,
        bool $nullable = false,
        array $joinCondition = [],
        bool $writeOnly = false
    ) {
        parent::__construct($sourceTable, $referenceTable, $property, $sourceColumn, $referenceColumn, $inBasic, $nullable, $joinCondition, 'CASCADE', $writeOnly);

        $this->mappingTable = $mappingTable;
        $this->mappingSourceColumn = $mappingSourceColumn;
        $this->mappingReferenceColumn = $mappingReferenceColumn;
        $this->property = $property;

        if ($this->referenceColumn === null) {
            $this->referenceColumn = 'id';
        }
        if ($this->sourceColumn === null) {
            $this->sourceColumn = 'id';
        }
        if ($this->mappingSourceColumn === null) {
            $this->mappingSourceColumn = $this->sourceTable . '_' . $this->sourceColumn;
        }
        if ($this->mappingReferenceColumn === null) {
            $this->mappingReferenceColumn = $this->referenceTable . '_' . $this->referenceColumn;
        }

    }
}

class ManyToOneAssociation extends Association
{
    public function __construct(
        string $sourceTable,
        string $referenceTable,
        string $property,
        bool $inBasic = false,
        ?string $sourceColumn = null,
        ?string $referenceColumn = null,
        bool $nullable = false,
        array $joinCondition = [],
        $onDelete = false,
        bool $writeOnly = false
    ) {
        parent::__construct($sourceTable, $referenceTable, $property, $sourceColumn, $referenceColumn, $inBasic, $nullable, $joinCondition, $onDelete, $writeOnly);

        if ($this->referenceColumn === null) {
            $this->referenceColumn = 'id';
        }
        if ($this->sourceColumn === null) {
            $this->sourceColumn = $this->referenceTable . '_id';
        }
    }
}