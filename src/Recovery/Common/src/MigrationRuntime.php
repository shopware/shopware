<?php declare(strict_types=1);

namespace Shopware\Recovery\Common;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;

class MigrationRuntime
{
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function migrate(?int $until = null, ?int $limit = null, ?array $identifiers = null): \Generator
    {
        $migrations = $this->getExecutableMigrations($until, $limit, $identifiers);

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

    public function migrateDestructive(?int $until = null, ?int $limit = null, ?array $identifiers = null): \Generator
    {
        $migrations = $this->getExecutableDestructiveMigrations($until, $limit, $identifiers);

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

    public function getExecutableMigrations(?int $until = null, ?int $limit = null, ?array $identifiers = null): array
    {
        $query = $this->getExecutableMigrationsBaseQuery($until, $limit, $identifiers)
            ->andWhere('`update` IS NULL');

        return $query
            ->execute()
            ->fetchAll(FetchMode::COLUMN);
    }

    public function getExecutableDestructiveMigrations(?int $until = null, ?int $limit = null, ?array $identifiers = null): array
    {
        return $this->getExecutableMigrationsBaseQuery($until, $limit, $identifiers)
            ->andWhere('`update` IS NOT NULL')
            ->andWhere('`update_destructive` IS NULL')
            ->execute()
            ->fetchAll(FetchMode::COLUMN);
    }

    private function getExecutableMigrationsBaseQuery(?int $until = null, ?int $limit = null, ?array $identifiers = null): QueryBuilder
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

        if ($identifiers) {
            $args = [];
            foreach ($identifiers as $identifier) {
                $identifier = addcslashes($identifier, '\\_%');
                $parameter = $query->createNamedParameter($identifier . '%');

                $args[] = '`class` LIKE ' . $parameter;
            }

            $query->andWhere($query->expr()->orX(...$args));
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
    }

    private function setExecutedDestructive(MigrationStep $migrationStep): void
    {
        $this->connection->executeUpdate('
            UPDATE `migration`
               SET `message` = NULL,
                   `update_destructive` = :dateTime
             WHERE `class` = :class',
            ['class' => \get_class($migrationStep), 'dateTime' => date(Defaults::STORAGE_DATE_FORMAT)]
        );
    }

    private function setExecuted(MigrationStep $migrationStep): void
    {
        $this->connection->executeUpdate('
            UPDATE `migration`
               SET `message` = NULL,
                   `update` = :dateTime
             WHERE `class` = :class',
            ['class' => \get_class($migrationStep), 'dateTime' => date(Defaults::STORAGE_DATE_FORMAT)]
        );
    }
}
