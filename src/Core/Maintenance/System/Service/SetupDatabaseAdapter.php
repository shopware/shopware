<?php declare(strict_types=1);

namespace Shopware\Core\Maintenance\System\Service;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Kernel;

/**
 * @internal
 *
 * @codeCoverageIgnore - Is tested by integration test, does not make sense to unit test
 * as the sole purpose of this class is to abstract DB interactions during setup
 */
#[Package('core')]
class SetupDatabaseAdapter
{
    public function dropDatabase(Connection $connection, string $database): void
    {
        $connection->executeStatement('DROP DATABASE IF EXISTS `' . $database . '`');
    }

    public function createDatabase(Connection $connection, string $database): void
    {
        $connection->executeStatement('CREATE DATABASE IF NOT EXISTS `' . $database . '` CHARACTER SET `utf8mb4` COLLATE `utf8mb4_unicode_ci`');
    }

    public function initializeShopwareDb(Connection $connection, ?string $database = null): bool
    {
        if (!$hasShopwareTables = $this->hasShopwareTables($connection, $database)) {
            $connection->executeStatement($this->getBaseSchema());
        }

        return !$hasShopwareTables;
    }

    public function hasShopwareTables(Connection $connection, ?string $database = null): bool
    {
        if ($database) {
            $connection->executeStatement('USE `' . $database . '`');
        }

        $tables = $connection->fetchFirstColumn('SHOW TABLES');

        if (\in_array('migration', $tables, true)) {
            return true;
        }

        return false;
    }

    public function getTableCount(Connection $connection, string $database): int
    {
        $connection->executeStatement('USE `' . $database . '`');

        $tables = $connection->fetchFirstColumn('SHOW TABLES');

        return \count($tables);
    }

    /**
     * @param list<string> $ignoredSchemas
     *
     * @return list<string>
     */
    public function getExistingDatabases(Connection $connection, array $ignoredSchemas): array
    {
        $query = $connection->createQueryBuilder()
            ->select('SCHEMA_NAME')
            ->from('information_schema.SCHEMATA');

        if (!empty($ignoredSchemas)) {
            $query->andWhere('SCHEMA_NAME NOT IN (:ignoredSchemas)')
                ->setParameter('ignoredSchemas', $ignoredSchemas, ArrayParameterType::STRING);
        }

        return $query->executeQuery()->fetchFirstColumn();
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
