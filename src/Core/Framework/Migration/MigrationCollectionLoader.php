<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Doctrine\MultiInsertQueryQueue;

class MigrationCollectionLoader
{
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

    public function syncMigrationCollection(): void
    {
        $migrations = $this->collection->getMigrationCollection();

        if (!$migrations) {
            return;
        }

        $this->addMigrationsToTable($migrations);
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
    private function addMigrationsToTable(array $migrations): void
    {
        $insertQuery = new MultiInsertQueryQueue($this->connection, 250, true);
        foreach ($migrations as $className => $migration) {
            $insertQuery->addInsert('migration', [
                '`class`' => $className,
                '`creation_timestamp`' => $migration->getCreationTimestamp(),
            ]);
        }
        $insertQuery->execute();
    }
}
