<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration;

use Doctrine\DBAL\Connection;
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

    public function __construct(Connection $connection, LoggerInterface $logger)
    {
        $this->connection = $connection;
        $this->logger = $logger;
    }

    public function migrate(bool $destructive, int $limit)
    {
        $this->ensureMigrationTableExists($this->connection);

        $migrations = $this->getMigrations($destructive, $limit);

        foreach ($migrations as $migration) {
            /** @var MigrationStep $migration */
            $migration = new $migration();

            try {
                if ($destructive) {
                    $migration->updateDestructive($this->connection);
                } else {
                    $migration->update($this->connection);
                }
            } catch (\Exception $e) {
                $this->setError($migration, $e->getMessage());
                $this->logger->error('Migration: "' . get_class($migration) . '" failed: "' . $e->getMessage() . '"');

                throw $e;
            }

            $this->setExecuted($migration, $destructive);
        }
    }

    public static function ensureMigrationTableExists(Connection $connection)
    {
        $connection->exec('
                CREATE TABLE IF NOT EXISTS `migration` (
                    `class` VARCHAR(255) NOT NULL,
                    `creation_time_stamp` INT(8) NOT NULL,
                    `update` TIMESTAMP(6) NULL DEFAULT NULL,
                    `update_destructive` TIMESTAMP(6) NULL DEFAULT NULL,
                    `message` TEXT DEFAULT NULL,
                    PRIMARY KEY (`class`)
                )
                COLLATE=\'utf8_unicode_ci\'
                ENGINE=InnoDB;
        ');
    }

    private function getMigrations(bool $destructive, int $limit)
    {
        $query = $this->connection->createQueryBuilder()
            ->select('`class`')
            ->from('migration')
            ->orderBy('`creation_time_stamp`', 'ASC')
            ->where('`update` IS NULL');

        if ($destructive) {
            $query->where('`update` IS NOT NULL')
                ->andWhere('`update_destructive` IS NULL');
        }

        if ($limit) {
            $query->setMaxResults($limit);
        }

        return $query->execute()->fetchAll(\PDO::FETCH_COLUMN);
    }

    private function setError(MigrationStep $migration, string $message)
    {
        $this->connection->update(
            'migration',
            [
                'message' => $message,
            ],
            [
                '`class`' => get_class($migration),
            ]
        );
    }

    private function setExecuted(MigrationStep $migrationStep, bool $destructive)
    {
        $query = $this->connection->createQueryBuilder()
            ->update('migration')
            ->set('message', 'NULL')
            ->where('`class` = :class')
            ->setParameter('class', get_class($migrationStep));

        if ($destructive) {
            $query->set('`update_destructive`', 'NOW(6)');
        } else {
            $query->set('`update`', 'NOW(6)');
        }

        $query->execute();
    }
}
