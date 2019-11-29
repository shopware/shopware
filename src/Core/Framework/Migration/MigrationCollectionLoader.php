<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\MultiInsertQueryQueue;

class MigrationCollectionLoader
{
    public const SHOPWARE_CORE_MIGRATION_IDENTIFIER = 'Shopware\\Core\\Migration';

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var MigrationCollection
     */
    private $collection;

    public function __construct(Connection $connection, MigrationCollection $collection)
    {
        $this->connection = $connection;
        $this->collection = $collection;
    }

    public function syncMigrationCollection(string $identifier = self::SHOPWARE_CORE_MIGRATION_IDENTIFIER): void
    {
        $migrations = $this->collection->getMigrationCollection();

        if (!$migrations) {
            return;
        }

        $this->addMigrationsToTable($migrations, $identifier);
    }

    /**
     * @return int[]
     */
    public function getActiveMigrationTimestamps(): array
    {
        return $this->collection->getActiveMigrationTimestamps();
    }

    /**
     * @param MigrationStep[] $migrations
     */
    private function addMigrationsToTable(array $migrations, string $identifier): void
    {
        $insertQuery = new MultiInsertQueryQueue($this->connection, 250, true);
        foreach ($migrations as $className => $migration) {
            if (mb_strpos($className, $identifier) !== false) {
                $insertQuery->addInsert('migration', [
                    '`class`' => $className,
                    '`creation_timestamp`' => $migration->getCreationTimestamp(),
                ]);
            }
        }
        $insertQuery->execute();
    }
}
