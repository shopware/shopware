<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\Exception\MigrateException;

#[Package('core')]
class MigrationRuntime
{
    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly LoggerInterface $logger
    ) {
    }

    public function migrate(MigrationSource $source, ?int $until = null, ?int $limit = null): \Generator
    {
        $migrations = $this->getExecutableMigrations($source, $until, $limit);

        $this->setDefaultStorageEngine();

        foreach ($migrations as $migration) {
            if (!class_exists($migration)) {
                $this->logger->notice(sprintf('Migration "%s" does not exists. Ignoring it', $migration));

                continue;
            }

            /** @var MigrationStep $migration */
            $migration = new $migration();

            try {
                $migration->update($this->connection);
            } catch (\Exception $e) {
                $this->logError($migration, $e->getMessage());

                $this->enrichException($e);

                throw $e;
            }

            $this->setExecuted($migration);
            yield $migration::class;
        }
    }

    public function migrateDestructive(MigrationSource $source, ?int $until = null, ?int $limit = null): \Generator
    {
        $migrations = $this->getExecutableDestructiveMigrations($source, $until, $limit);

        $this->setDefaultStorageEngine();

        foreach ($migrations as $migration) {
            if (!class_exists($migration)) {
                $this->logger->notice(sprintf('Migration "%s" does not exists. Ignoring it', $migration));

                continue;
            }

            /** @var MigrationStep $migration */
            $migration = new $migration();

            try {
                $migration->updateDestructive($this->connection);
            } catch (\Exception $e) {
                $this->logError($migration, $e->getMessage());

                throw $e;
            }

            $this->setExecutedDestructive($migration);
            yield $migration::class;
        }
    }

    /**
     * @return list<class-string<MigrationStep>>
     */
    public function getExecutableMigrations(MigrationSource $source, ?int $until = null, ?int $limit = null): array
    {
        return $this->getExecutableMigrationsBaseQuery($source, $until, $limit)
            ->andWhere('`update` IS NULL')
            ->executeQuery()
            ->fetchFirstColumn();
    }

    /**
     * @return list<class-string<MigrationStep>>
     */
    public function getExecutableDestructiveMigrations(MigrationSource $source, ?int $until = null, ?int $limit = null): array
    {
        return $this->getExecutableMigrationsBaseQuery($source, $until, $limit)
            ->andWhere('`update` IS NOT NULL')
            ->andWhere('`update_destructive` IS NULL')
            ->executeQuery()
            ->fetchFirstColumn();
    }

    public function getTotalMigrationCount(MigrationSource $source, ?int $until = null, ?int $limit = null): int
    {
        return (int) $this->getExecutableMigrationsBaseQuery($source, $until, $limit)
            ->select('COUNT(*)')
            ->executeQuery()
            ->fetchOne();
    }

    protected function setDefaultStorageEngine(): void
    {
        $this->connection->executeStatement('SET default_storage_engine=InnoDB');
    }

    private function getExecutableMigrationsBaseQuery(MigrationSource $source, ?int $until = null, ?int $limit = null): QueryBuilder
    {
        $query = $this->connection->createQueryBuilder()
            ->select('`class`')
            ->from('migration')
            ->where('`class` REGEXP :pattern')
            ->orderBy('`creation_timestamp`', 'ASC');

        if ($until !== null) {
            $query->andWhere('`creation_timestamp` <= :timestamp');
            $query->setParameter('timestamp', $until);
        }

        if ($limit > 0) {
            $query->setMaxResults($limit);
        }

        $query->setParameter('pattern', $source->getNamespacePattern());

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
                '`class`' => $migration::class,
            ]
        );

        $this->logger->error('Migration: "' . $migration::class . '" failed: "' . $message . '"');
    }

    private function setExecutedDestructive(MigrationStep $migrationStep): void
    {
        $this->connection->executeStatement(
            'UPDATE `migration`
               SET `message` = NULL,
                   `update_destructive` = NOW(6)
             WHERE `class` = :class',
            ['class' => $migrationStep::class]
        );
    }

    private function setExecuted(MigrationStep $migrationStep): void
    {
        $this->connection->executeStatement(
            'UPDATE `migration`
               SET `message` = NULL,
                   `update` = NOW(6)
             WHERE `class` = :class',
            ['class' => $migrationStep::class]
        );
    }

    private function enrichException(\Exception $e): void
    {
        if ($e->getCode() !== 0) {
            return;
        }

        if (preg_match('/SQLSTATE\[23000\]:.*(1452).*a foreign key constraint/', $e->getMessage())) {
            $matches = [];
            preg_match(
                '/TABLE.*?`(.*?)`.*? (REFERENCES|constraint).*?`(.*?)`/',
                (string) preg_replace(["/\r|\n/", '/ +/'], ['', ' '], $e->getMessage()),
                $matches
            );

            if (isset($matches[1]) && isset($matches[2]) && $matches[2] === 'REFERENCES' && isset($matches[3])) {
                throw new MigrateException(
                    'The migration failed due to inconsistent data. You can try to check the table `' . $matches[1]
                    . '` for entries that do not match the entries in table `' . $matches[3] . '`.',
                    $e
                );
            } elseif (isset($matches[1])) {
                throw new MigrateException(
                    'The migration failed due to inconsistent data. You can try to check the table `' . $matches[1]
                    . '` for entries that do not match the entries in the table referenced in the foreign key.',
                    $e
                );
            }
        }
    }
}
