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

        // Indexes are not supported, so we remove them from both tables
        $originalTableSchema = $this->dropIndexes($originalTableSchema);

        $tableSchema = $this->schemaBuilder->buildSchemaOfDefinition($definition);

        $tableSchema = $this->dropIndexes($tableSchema);

        $comparatorConfig = (new ComparatorConfig())->withReportModifiedIndexes(false);
        return $this->getPlatform()->getAlterTableSQL(
            $schemaManager->createComparator($comparatorConfig)->compareTables($originalTableSchema, $tableSchema)
        );
    }

    /**
     * @return list<string>
     */
    private function getCreateTableQueries(EntityDefinition $definition): array
    {
        $tableSchema = $this->schemaBuilder->buildSchemaOfDefinition($definition);

        $tableSchema = $this->dropIndexes($tableSchema);

        return $this->getPlatform()->getCreateTableSQL($tableSchema);
    }

    private function getPlatform(): AbstractPlatform
    {
        return $this->connection->getDatabasePlatform();
    }

    private function dropIndexes(Table $table): Table
    {
        $tableEditor = $table->edit();
        foreach ($table->getIndexes() as $index) {
            /** @phpstan-ignore method.deprecated (if can be removed with DBAL 5.0 as primaries won't be inlcuded anymore) */
            if ($index->isPrimary()) {
                continue;
            }

            $tableEditor->dropIndex($index->getObjectName());
        }

        return $tableEditor->create();
    }
}
