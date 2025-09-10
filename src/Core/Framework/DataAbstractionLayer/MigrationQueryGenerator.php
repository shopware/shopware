<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\ComparatorConfig;
use Doctrine\DBAL\Schema\Table;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\SchemaBuilder;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
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
        $tableExists = $this->connection->createSchemaManager()->tablesExist([$entityDefinition->getEntityName()]);

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
        $schemaManager = $this->connection->createSchemaManager();
        $originalTableSchema = $schemaManager->introspectTable($definition->getEntityName());

        // Indexes are not supported, so we remove them from both tables
        $this->dropIndexes($originalTableSchema);

        $tableSchema = $this->schemaBuilder->buildSchemaOfDefinition($definition);

        $this->dropIndexes($tableSchema);

        // ReportModifiedIndexes is no longer supported in the Comparator as of doctrine/dbal 5.x
        // but schemaManager->createComparator() does not allow us to pass a config
        $config = new ComparatorConfig();
        $config = $config->withReportModifiedIndexes(false);
        $comparator = new Comparator($this->getPlatform(), $config);

        return $this->getPlatform()->getAlterTableSQL($comparator->compareTables($originalTableSchema, $tableSchema));
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
        return $this->connection->getDatabasePlatform();
    }

    /**
     * Never try to drop primary key, they are listed in indexes until doctrine/dbal 5.x,
     * $table->getPrimaryKeyConstraint() cannot be matched against index name,
     * a primary key is by default named 'primary' in doctrine/dbal 4.x within index list.
     */
    private function dropIndexes(Table $table): void
    {
        foreach ($table->getIndexes() as $index) {
            // Skip primary key, this is not an index that can be dropped, it won't be listed in doctrine/dbal 5.x
            if ($index->getName() === 'primary') {
                continue;
            }

            $table->dropIndex($index->getName());
        }
    }
}
