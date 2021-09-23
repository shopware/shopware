<?php declare(strict_types=1);

namespace Shopware\Core\Maintenance\System\Service;

use Doctrine\DBAL\Connection;
use Shopware\Core\Kernel;

class DatabaseInitializer
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function dropDatabase(string $database): void
    {
        $this->connection->executeStatement('DROP DATABASE IF EXISTS `' . $database . '`');
    }

    public function createDatabase(string $database): void
    {
        $this->connection->executeStatement('CREATE DATABASE IF NOT EXISTS `' . $database . '` CHARACTER SET `utf8mb4` COLLATE `utf8mb4_unicode_ci`');
    }

    public function initializeShopwareDb(?string $database = null): bool
    {
        if (!$hasShopwareTables = $this->hasShopwareTables($database)) {
            $this->connection->executeStatement($this->getBaseSchema());
        }

        return !$hasShopwareTables;
    }

    public function hasShopwareTables(?string $database = null): bool
    {
        if ($database) {
            $this->connection->executeStatement('USE `' . $database . '`');
        }

        $tables = $this->connection->fetchFirstColumn('SHOW TABLES');

        if (\in_array('migration', $tables, true)) {
            return true;
        }

        return false;
    }

    public function getTableCount(string $database): int
    {
        $this->connection->executeStatement('USE `' . $database . '`');

        $tables = $this->connection->fetchFirstColumn('SHOW TABLES');

        return \count($tables);
    }

    public function getExistingDatabases(array $ignoredSchemas): array
    {
        $query = $this->connection->createQueryBuilder()
            ->select('SCHEMA_NAME')
            ->from('information_schema.SCHEMATA');

        if (!empty($ignoredSchemas)) {
            $query->andWhere('SCHEMA_NAME NOT IN (:ignoredSchemas)')
                ->setParameter('ignoredSchemas', $ignoredSchemas, Connection::PARAM_STR_ARRAY);
        }

        return $query->execute()->fetchFirstColumn();
    }

    private function getBaseSchema(): string
    {
        $kernelClass = new \ReflectionClass(Kernel::class);
        $directory = \dirname((string) $kernelClass->getFileName());

        $path = $directory . '/schema.sql';
        if (!is_readable($path) || is_dir($path)) {
            throw new \RuntimeException('schema.sql not found or readable in ' . $directory);
        }

        return (string) file_get_contents($path);
    }
}
