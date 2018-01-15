<?php

class Context
{
    /**
     * @var Association[]
     */
    public $associations;

    /**
     * @var array
     */
    public $basicAssociations;

    /**
     * @var array
     */
    public $writeOnly;

    /**
     * @var array
     */
    public $inject;

    /**
     * @var array
     */
    public $htmlFields;

    /**
     * @var array
     */
    public $virtualForeignKeys;

    /**
     * @var array
     */
    public $prevent;

    public function __construct(array $associations, array $basicAssociations, array $writeOnly, array $inject, array $htmlFields = [], array $virtualForeignKeys = [], array $prevent = [])
    {
        $this->associations = $associations;
        $this->basicAssociations = $basicAssociations;
        $this->writeOnly = $writeOnly;
        $this->inject = $inject;
        $this->htmlFields = $htmlFields;
        $this->virtualForeignKeys = $virtualForeignKeys;
        $this->prevent = $prevent;
    }

    public function getForeignKeys(string $table): array
    {
        if (array_key_exists($table, $this->virtualForeignKeys)) {
            return $this->virtualForeignKeys[$table];
        }
        return [];
    }

    public function isHtmlField(string $table, string $columnName): bool
    {
        $key = implode('.', [$table, $columnName]);
        return in_array($key, $this->htmlFields, true);
    }

    public function getCollectionInjection(string $table): string
    {
        if (!array_key_exists($table, $this->inject)) {
            return '';
        }
        $inject = $this->inject[$table];
        if (!array_key_exists('collection', $inject)) {
            return '';
        }
        return $inject['collection'];
    }

    public function getStructInjection(string $table): string
    {
        if (!array_key_exists($table, $this->inject)) {
            return '';
        }
        $inject = $this->inject[$table];
        if (!array_key_exists('struct', $inject)) {
            return '';
        }
        return $inject['struct'];
    }

    public function writeOnly(Association $association): bool
    {
        if (!array_key_exists($association->sourceTable, $this->writeOnly)) {
            return false;
        }
        $prevent = $this->writeOnly[$association->sourceTable];

        if (in_array($association->property, $prevent, true)) {
            return true;
        }
        if ($association instanceof OneToManyAssociation || $association instanceof ManyToManyAssociation) {
            return in_array($association->propertyPlural, $prevent, true);
        }
        return false;
    }

    public function prevent(Association $association): bool
    {
        if (!array_key_exists($association->sourceTable, $this->prevent)) {
            return false;
        }
        $prevent = $this->prevent[$association->sourceTable];

        if (in_array($association->property, $prevent, true)) {
            return true;
        }
        if ($association instanceof OneToManyAssociation || $association instanceof ManyToManyAssociation) {
            return in_array($association->propertyPlural, $prevent, true);
        }
        return false;
    }


    public function loadInBasic(Association $association): bool
    {
        if (!array_key_exists($association->sourceTable, $this->basicAssociations)) {
            return false;
        }
        $basics = $this->basicAssociations[$association->sourceTable];

        return in_array($association->property, $basics, true);
    }

    public function getAssociationsForTable(string $table): array
    {
        return array_filter(
            $this->associations,
            function(Association $association) use ($table) {
                return $association->sourceTable === $table;
            }
        );
    }

    public function getBasicAssociations(string $table): array
    {
        return array_filter(
            $this->getAssociationsForTable($table),
            function(Association $association) {
                return $association->inBasic;
            }
        );
    }

    public function getDetailAssociations(string $table): array
    {
        return array_filter(
            $this->getAssociationsForTable($table),
            function(Association $association) {
                return !$association->inBasic;
            }
        );
    }

    public function getAssociationOfType(string $table, string $class): array
    {
        return array_filter(
            $this->getAssociationsForTable($table),
            function(Association $association) use ($class) {
                return $association instanceof $class;
            }
        );
    }

    public function getAssociationForColumn($tableName, $referenceColumnName)
    {
        $associations = $this->getAssociationsForTable($tableName);
        /** @var Association $association */
        foreach ($associations as $association) {
            if ($association->sourceColumn === $referenceColumnName) {
                return $association;
            }
        }

        return null;
    }

    public function hasManyToManyOverride(
        string $sourceTable,
        string $referenceTableName
    ) {
        $associations = $this->getAssociationsForTable($sourceTable);

        /** @var Association $association */
        foreach ($associations as $association) {
            if ($association->referenceTable === $referenceTableName) {
                return $association;
            }
        }
        return null;
    }

    public function getAssociationForForeignKey(
        string $sourceTable,
        string $sourceColumn,
        string $referenceTableName,
        string $referenceColumnName
    ): ?\Association
    {
        $associations = $this->getAssociationsForTable($sourceTable);

        /** @var Association $association */
        foreach ($associations as $association) {

            if ($association instanceof ManyToManyAssociation) {
                if ($association->mappingTable !== $referenceTableName) {
                    continue;
                }
                if ($association->mappingSourceColumn !== $referenceColumnName) {
                    continue;
                }
                if ($association->sourceColumn !== $sourceColumn) {
                    continue;
                }
                return $association;
            }

            if ($association->sourceColumn !== $sourceColumn) {
                continue;
            }
            if ($association->referenceTable !== $referenceTableName) {
                continue;
            }
            if ($association->referenceColumn !== $referenceColumnName) {
                continue;
            }
            return $association;
        }
        return null;
    }

    public function isMappingTable($tableName)
    {
        foreach ($this->associations as $association) {
            if (!$association instanceof ManyToManyAssociation) {
                continue;
            }
            if ($association->mappingTable === $tableName) {
                return true;
            }
        }
        return false;
    }

    public function getDefinitionInjection(string $tableName): string
    {
        if (!array_key_exists($tableName, $this->inject)) {
            return '';
        }

        $inject = $this->inject[$tableName];

        if (!array_key_exists('definition', $inject)) {
            return '';
        }

        return $inject['definition'];
    }
}