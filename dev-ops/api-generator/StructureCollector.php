<?php

use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;

class StructureCollector
{
    /**
     * @var AbstractSchemaManager
     */
    private $schemaManager;

    public function __construct(AbstractSchemaManager $schemaManager)
    {
        $this->schemaManager = $schemaManager;
    }

    public function collect(array $tables, Context $context): array
    {
        /**@var $definitions TableDefinition[]*/
        $definitions = [];
        foreach ($tables as $table) {
            $definition = new TableDefinition();
            $definition->tableName = $table;
            $definition->bundle = Util::getBundleName($table);
            $definition->domainName = Util::getTableDomainName($table);
            $definition->domainNameInPlural = Util::buildDomainPlural($definition->domainName);
            $definition->isMappingTable = $context->isMappingTable($table);
            $definitions[$table] = $definition;
        }

        foreach ($tables as $table) {
            $definition = $definitions[$table];
            $definition->columns = $this->readColumns($table, $context);
        }

        foreach ($tables as $table) {
            $definition = $definitions[$table];
            $definition->associations = array_merge(
                $definition->associations,
                $this->readAssociations($definitions, $definition, $context)
            );
        }

        foreach ($tables as $table) {
            $definition = $definitions[$table];
            $definition->associations = array_filter(
                $definition->associations,
                function(Association $association) use ($context) {
                    return !$context->prevent($association);
                }
            );
        }

        foreach ($definitions as $definition) {
            $sorted = array_merge(
                array_filter($definition->associations, function(Association $association) {
                    return $association instanceof OneToOneAssociation;
                }),
                array_filter($definition->associations, function(Association $association) {
                    return $association instanceof ManyToOneAssociation;
                }),
                array_filter($definition->associations, function(Association $association) {
                    return $association instanceof OneToManyAssociation;
                }),
                array_filter($definition->associations, function(Association $association) {
                    return $association instanceof ManyToManyAssociation;
                })
            );
            $definition->associations = $sorted;

            $sorted = array_merge(
                array_filter($definition->columns, function(ColumnDefinition $column) {
                    return $column->isPrimaryKey;
                }),
                array_filter($definition->columns, function(ColumnDefinition $column) {
                    return $column->isForeignKey;
                }),
                array_filter($definition->columns, function(ColumnDefinition $column) {
                    return !$column->isPrimaryKey && !$column->isForeignKey && $column->required;
                }),
                array_filter($definition->columns, function(ColumnDefinition $column) {
                    return !$column->isPrimaryKey && !$column->isForeignKey && !$column->required;
                })
            );
            $definition->columns = $sorted;
        }

        return $definitions;
    }

    protected function readColumns(string $table, Context $context): array
    {
        $indexes = $this->schemaManager->listTableIndexes($table);

        $foreignKeys = [];
        foreach ($this->schemaManager->listTableForeignKeys($table) as $foreignKey) {
            $foreignKeys[$foreignKey->getLocalColumns()[0]] = [
                $foreignKey->getForeignTableName(),
                $foreignKey->getForeignColumns()[0]
            ];
        }

        $columns = [];

        foreach ($this->schemaManager->listTableColumns($table) as $column) {
            $definition = new ColumnDefinition();

            $definition->table = $table;
            $definition->name = $column->getName();
            $definition->propertyName = Util::createPropertyName($table, $column->getName());
            $definition->propertyNamePlural = Util::buildDomainPlural($definition->propertyName);

            $definition->isForeignKey = array_key_exists($column->getName(), $foreignKeys);
            if ($definition->isForeignKey) {
                $key = $foreignKeys[$column->getName()];
                $definition->foreignKeyTable = $key[0];
                $definition->foreignKeyColumn = $key[1];
            }

            $definition->isPrimaryKey = $this->isPrimary($column, $indexes);

            $definition->type = $this->getColumnType($table, $column);
            if ($definition->type === null) {
                continue;
            }
            $definition->allowNull = !$column->getNotnull();
            $definition->allowHtml = $context->isHtmlField($table, $column->getName());

            $definition->required = ($column->getNotnull() && $column->getDefault() === null);
            if (!$definition->required) {
                $definition->hasDefault = ($column->getDefault() !== null);
                $definition->default = $column->getDefault();
            }

            $columns[] = $definition;
        }

        return $columns;
    }

    protected function readAssociations(array $definitions, TableDefinition $tableDefinition, Context $context)
    {
        // prepare data through schema manager
        $columns = $this->schemaManager->listTableColumns($tableDefinition->tableName);
        $rawForeignKeys = $this->schemaManager->listTableForeignKeys($tableDefinition->tableName);

        $foreignKeys = [];
        foreach ($rawForeignKeys as $foreignKey) {
            $foreignKeys[$foreignKey->getLocalColumns()[0]] = [$foreignKey->getForeignTableName(), $foreignKey->getForeignColumns()[0]];
        }

        $existing = $context->getAssociationsForTable($tableDefinition->tableName);

        $manyToOne = $this->getToOneAssociations($tableDefinition, $columns, $foreignKeys, $context);

        $existing = array_merge($existing, $manyToOne);

        $this->addInverseSideAssociations($definitions, $tableDefinition, $context, $foreignKeys);

        $translationTable = $tableDefinition->tableName . '_translation';

        if (!array_key_exists($translationTable, $definitions)) {
            return $existing;
        }
        /** @var TableDefinition $translationDefinition */
        $translationDefinition = $definitions[$translationTable];

        foreach ($translationDefinition->columns as $column) {
            if ($column->isPrimaryKey || $column->isForeignKey) {
                continue;
            }

            $new = clone $column;
            $new->isTranslationField = true;
            $new->translationTable = $translationTable;
            $tableDefinition->columns[] = $new;
        }

        return $existing;
    }

    private function isPrimary(Column $column, array $indexes): bool
    {
        if ($column->getName() === 'uuid') {
            return true;
        }

        if (!isset($indexes['primary'])) {
            return false;
        }

        /** @var \Doctrine\DBAL\Schema\Index $primaryIndex */
        $primaryIndex = $indexes['primary'];

        /** @var \Doctrine\DBAL\Schema\Identifier $indexColumn */
        foreach ($primaryIndex->getColumns() as $indexColumn) {
            if ($indexColumn === $column->getName()) {
                return true;
            }
        }

        return false;
    }

    private function getColumnType(string $table, Column $column)
    {
        switch ($column->getType()) {
            case 'Integer':
                return 'IntField';
            case 'DateTime':
            case 'Date':
                return 'DateField';
            case 'Text':
                return 'LongTextField';
            case 'String':
                return 'StringField';
            case 'Float':
            case 'Decimal':
                return 'FloatField';
            case 'Boolean':
                return 'BoolField';
            case 'Json':
                return 'ArrayField';
            default:
                echo "ERROR: unmapped type {$column->getType()}\n";
                return '';
        }
    }

    /**
     * @param TableDefinition $tableDefinition
     * @param Column[] $columns
     * @param array[] $foreignKeys
     * @return array
     */
    protected function getToOneAssociations(
        TableDefinition $tableDefinition,
        array $columns,
        array $foreignKeys,
        Context $context
    ): array
    {
        $manyToOne = [];
        foreach ($columns as $column) {
            if (!array_key_exists($column->getName(), $foreignKeys)) {
                continue;
            }

            list($referenceTableName, $referenceColumnName) = $foreignKeys[$column->getName()];

            $domain = Util::createPropertyName($tableDefinition->tableName, $column->getName());
            if ($domain === $tableDefinition->domainName) {
                $domain = 'parent';
            } else {
                $domain = Util::createAssociationPropertyName($tableDefinition->tableName, $column->getName());
            }

            $association = new ManyToOneAssociation(
                $tableDefinition->tableName,
                $referenceTableName,
                $domain,
                false,
                $column->getName(),
                $referenceColumnName,
                !$column->getNotnull()
            );
            $association->inBasic = $context->loadInBasic($association);

            $manyToOne[] = $association;
        }

        return $manyToOne;
    }


    /**
     * @param array $definitions
     * @param TableDefinition $tableDefinition
     * @param Context $context
     * @param $foreignKeys
     */
    protected function addInverseSideAssociations(
        array $definitions,
        TableDefinition $tableDefinition,
        Context $context,
        $foreignKeys
    ): void {
        $a = $tableDefinition->tableName === 'product';

        //build inverse side for one to many associations
        //source is now reference and reference is now source
        foreach ($foreignKeys as $sourceColumnName => $foreignKey) {
            list($referenceTableName, $referenceColumnName) = $foreignKey;

            if (!isset($definitions[$referenceTableName])) {
                continue;
            }

            $override = $context->getAssociationForForeignKey(
                $referenceTableName,
                $referenceColumnName,
                $tableDefinition->tableName,
                $sourceColumnName
            );

            if ($override !== null) {
                continue;
            }

            $override = $context->hasManyToManyOverride($referenceTableName, $tableDefinition->tableName);
            if ($override !== null) {
                continue;
            }

            /** @var TableDefinition $referenceDefinition */
            $referenceDefinition = $definitions[$referenceTableName];
            $property = Util::createAssociationPropertyName($referenceDefinition->tableName, $tableDefinition->tableName);

            $association = new OneToManyAssociation(
                $referenceTableName,
                $tableDefinition->tableName,
                $property,
                false,
                $referenceColumnName,
                $sourceColumnName
            );

            $association->inBasic = $context->loadInBasic($association);

            $referenceDefinition->associations[] = $association;
        }
    }
}