<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Table;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\SchemaBuilder;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
class MigrationQueryGenerator
{
    public function __construct(private readonly Connection $connection, private readonly SchemaBuilder $schemaBuilder)
    {
    }

    /**
     * Generates the SQL queries for the given entity definition based on the current database schema.
     * If the definition was updated it will generate the queries to update the schema.
     * If the definition was created it will generate the queries to create the schema.
     *
     * @return string[]
     */
    public function generateQueries(EntityDefinition $entityDefinition): array
    {
        $tableExists = $this->connection->createSchemaManager()->tablesExist($entityDefinition->getEntityName());

        if ($tableExists) {
            return $this->getAlterTableQueries($entityDefinition);
        }

        return $this->getCreateTableQueries($entityDefinition);
    }

    /**
     * @return string[]
     */
    private function getAlterTableQueries(EntityDefinition $definition): array
    {
        $originalTableSchema = $this->connection->createSchemaManager()->introspectTable($definition->getEntityName());

        // Indexes are not supported, so we remove them from both tables
        $this->dropIndexes($originalTableSchema);

        $tableSchema = $this->schemaBuilder->buildSchemaOfDefinition($definition);

        $this->dropIndexes($tableSchema);

        return $this->getPlatform()->getAlterTableSQL((new Comparator())->compareTables($originalTableSchema, $tableSchema));
    }

    /**
     * @return string[]
     */
    private function getCreateTableQueries(EntityDefinition $definition): array
    {
        $tableSchema = $this->schemaBuilder->buildSchemaOfDefinition($definition);

        $this->dropIndexes($tableSchema);

        return $this->getPlatform()->getCreateTableSQL($tableSchema);
    }

    private function getPlatform(): AbstractPlatform
    {
        $platform = $this->connection->getDatabasePlatform();

        if (!$platform instanceof AbstractPlatform) {
            throw DataAbstractionLayerException::databasePlatformInvalid();
        }

        return $platform;
    }

    private function dropIndexes(Table $table): void
    {
        foreach ($table->getIndexes() as $index) {
            if ($index->isPrimary()) {
                continue;
            }

            $table->dropIndex($index->getName());
        }
    }
}
