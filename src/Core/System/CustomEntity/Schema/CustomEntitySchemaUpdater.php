<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Schema;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Schema;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Lock\LockFactory;

/**
 * @internal
 *
 * @phpstan-import-type CustomEntityField from SchemaUpdater
 */
#[Package('framework')]
class CustomEntitySchemaUpdater
{
    private const COMMENT = 'custom-entity-element';

    public function __construct(
        private readonly Connection $connection,
        private readonly LockFactory $lockFactory,
        private readonly SchemaUpdater $schemaUpdater
    ) {
    }

    public function update(): void
    {
        $this->connection->getDatabasePlatform()->registerDoctrineTypeMapping('enum', 'string');

        $this->lock(function (): void {
            /** @var list<array{name: string, fields: string}> $tables */
            $tables = $this->connection->fetchAllAssociative('SELECT name, fields FROM custom_entity');

            $schema = $this->connection->createSchemaManager()->introspectSchema();

            $this->cleanup($schema);

            $this->schemaUpdater->applyCustomEntities($schema, $tables);

            $this->applyNewSchema($schema);
        });
    }

    private function lock(\Closure $closure): void
    {
        $lock = $this->lockFactory->createLock('custom-entity::schema-update', 30);

        if ($lock->acquire(true)) {
            $closure();

            $lock->release();
        }
    }

    private function applyNewSchema(Schema $update): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        $baseSchema = $schemaManager->introspectSchema();
        $queries = $this->getPlatform()->getAlterSchemaSQL($schemaManager->createComparator()->compareSchemas($baseSchema, $update));

        // Store the current value of foreign key checks and disable them
        // This is a temporary fix until there is answer for https://github.com/doctrine/dbal/issues/6706
        $currentForeignKeyChecks = $this->connection->fetchOne('SELECT @@FOREIGN_KEY_CHECKS');
        $this->connection->executeQuery('SET FOREIGN_KEY_CHECKS = 0');

        foreach ($queries as $query) {
            try {
                $this->connection->executeStatement($query);
            } catch (Exception $e) {
                // there seems to be a timing issue in sql when dropping a foreign key which relates to an index.
                // Sometimes the index exists no more when doctrine tries to drop it after dropping the foreign key.
                if (!\str_contains($e->getMessage(), 'An exception occurred while executing \'DROP INDEX IDX_')) {
                    // Restore the original value of foreign key checks
                    $this->connection->executeQuery('SET FOREIGN_KEY_CHECKS = ' . $currentForeignKeyChecks);

                    throw $e;
                }
            }
        }
        // Restore the original value of foreign key checks
        $this->connection->executeQuery('SET FOREIGN_KEY_CHECKS = ' . $currentForeignKeyChecks);
    }

    private function getPlatform(): AbstractPlatform
    {
        return $this->connection->getDatabasePlatform();
    }

    private function cleanup(Schema $schema): void
    {
        foreach ($schema->getTables() as $table) {
            if ($table->getComment() === self::COMMENT) {
                $schema->dropTable($table->getName());

                continue;
            }

            foreach ($table->getForeignKeys() as $foreignKey) {
                if (\str_starts_with($foreignKey->getName(), 'fk_ce_')) {
                    $table->removeForeignKey($foreignKey->getName());
                }
            }

            foreach ($table->getColumns() as $column) {
                if ($column->getComment() === self::COMMENT) {
                    $table->dropColumn($column->getName());
                }
            }
        }
    }
}
