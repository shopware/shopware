<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\Query\QueryBuilder;
use Psr\Log\LoggerInterface;

class MigrationRuntime
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        Connection $connection,
        LoggerInterface $logger
    ) {
        $this->connection = $connection;
        $this->logger = $logger;
    }

    public function migrate(?int $until = null, ?int $limit = null): \Generator
    {
        $migrations = $this->getExecutableMigrations($until, $limit);

        $this->setDefaultStorageEngine();

        foreach ($migrations as $migration) {
            /** @var MigrationStep $migration */
            $migration = new $migration();

            try {
                $migration->update($this->connection);
            } catch (\Exception $e) {
                $this->logError($migration, $e->getMessage());

                throw $e;
            }

            $this->setExecuted($migration);
            yield \get_class($migration);
        }
    }

    public function migrateDestructive(?int $until = null, ?int $limit = null): \Generator
    {
        $migrations = $this->getExecutableDestructiveMigrations($until, $limit);

        $this->setDefaultStorageEngine();

        foreach ($migrations as $migration) {
            /** @var MigrationStep $migration */
            $migration = new $migration();

            try {
                $migration->updateDestructive($this->connection);
            } catch (\Exception $e) {
                $this->logError($migration, $e->getMessage());

                throw $e;
            }

            $this->setExecutedDestructive($migration);
            yield \get_class($migration);
        }
    }

    public function getExecutableMigrations(?int $until = null, ?int $limit = null): array
    {
        return $this->getExecutableMigrationsBaseQuery($until, $limit)
            ->andWhere('`update` IS NULL')
            ->execute()
            ->fetchAll(FetchMode::COLUMN);
    }

    public function getExecutableDestructiveMigrations(?int $until = null, ?int $limit = null): array
    {
        return $this->getExecutableMigrationsBaseQuery($until, $limit)
            ->andWhere('`update` IS NOT NULL')
            ->andWhere('`update_destructive` IS NULL')
            ->execute()
            ->fetchAll(FetchMode::COLUMN);
    }

    protected function setDefaultStorageEngine(): void
    {
        $this->connection->exec('SET default_storage_engine=InnoDB');
    }

    private function getExecutableMigrationsBaseQuery(?int $until = null, ?int $limit = null): QueryBuilder
    {
        $query = $this->connection->createQueryBuilder()
            ->select('`class`')
            ->from('migration')
            ->orderBy('`creation_timestamp`', 'ASC');

        if ($until !== null) {
            $query->where('`creation_timestamp` <= :timestamp');
            $query->setParameter('timestamp', $until);
        }

        if ($limit > 0) {
            $query->setMaxResults($limit);
        }

        return $query;
    }

    private function logError(MigrationStep $migration, string $message): void
    {
        $this->connection->update(
            'migration',
            [
                '`message`' => utf8_encode($message),
            ],
            [
                '`class`' => \get_class($migration),
            ]
        );

        $this->logger->error('Migration: "' . \get_class($migration) . '" failed: "' . $message . '"');
    }

    private function setExecutedDestructive(MigrationStep $migrationStep): void
    {
        $this->connection->executeUpdate(
            'UPDATE `migration`
               SET `message` = NULL,
                   `update_destructive` = NOW(6)
             WHERE `class` = :class',
            ['class' => \get_class($migrationStep)]
        );
    }

    private function setExecuted(MigrationStep $migrationStep): void
    {
        $this->connection->executeUpdate(
            'UPDATE `migration`
               SET `message` = NULL,
                   `update` = NOW(6)
             WHERE `class` = :class',
            ['class' => \get_class($migrationStep)]
        );
    }
}
