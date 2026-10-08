<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\ComparatorConfig;
use Doctrine\DBAL\Schema\Table;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\SchemaBuilder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Database\TableHelper;

/**
 * @internal
 */
#[Package('framework')]
class MigrationQueryGenerator
{
    public function __construct(
        private readonly Connection $connection,
        private readonly SchemaBuilder $schemaBuilder
    ) {
    }

    /**
     * Generates the SQL queries for the given entity definition based on the current database schema.
     * If the definition was updated, it will generate the queries to update the schema.
     * If the definition was created, it will generate the queries to create the schema.
     *
     * @return list<string>
     */
    public function generateQueries(EntityDefinition $entityDefinition): array
    {
        if (TableHelper::tableExists($this->connection, $entityDefinition->getEntityName())) {
            return $this->getAlterTableQueries($entityDefinition);
        }

        return $this->getCreateTableQueries($entityDefinition);
    }

    /**
     * @return list<string>
     */
    private function getAlterTableQueries(EntityDefinition $definition): array
    {
        $schemaManager = $this->connection->createSchemaManager();
        $originalTableSchema = $schemaManager->introspectTableByUnquotedName($definition->getEntityName());

        $tableSchema = $this->schemaBuilder->buildSchemaOfDefinition($definition);

        $comparator = $schemaManager->createComparator((new ComparatorConfig())->withReportModifiedIndexes(false));

        $foreignKeyDiff = $comparator->compareTables($originalTableSchema, $tableSchema);

        $platform = $this->getPlatform();

        $tableName = $tableSchema->getObjectName()->toSQL($platform);

        $queries = [];
        foreach ($foreignKeyDiff->getDroppedForeignKeyConstraintNames() as $name) {
            $queries[] = $platform->getDropForeignKeySQL($name->toSQL($platform), $tableName);
        }

        $queries = array_merge($queries, $platform->getAlterTableSQL(
            $comparator->compareTables($this->dropIndexes($originalTableSchema), $this->dropIndexes($tableSchema))
        ));

        foreach ($foreignKeyDiff->getAddedForeignKeys() as $foreignKey) {
            $queries[] = $platform->getCreateForeignKeySQL($foreignKey, $tableName);
        }

        return $queries;
    }

    /**
     * @return list<string>
     */
    private function getCreateTableQueries(EntityDefinition $definition): array
    {
        $tableSchema = $this->schemaBuilder->buildSchemaOfDefinition($definition);

        $platform = $this->getPlatform();
        $queries = $platform->getCreateTableSQL($this->dropIndexes($tableSchema));

        foreach ($tableSchema->getForeignKeys() as $foreignKey) {
            $queries[] = $platform->getCreateForeignKeySQL($foreignKey, $tableSchema->getObjectName()->toSQL($platform));
        }

        return $queries;
    }

    private function getPlatform(): AbstractPlatform
    {
        return $this->connection->getDatabasePlatform();
    }

    private function dropIndexes(Table $table): Table
    {
        // Foreign keys are handled separately, otherwise DBAL recreates their backing indexes.
        return $table->edit()->setIndexes()->setForeignKeyConstraints()->create();
    }
}
