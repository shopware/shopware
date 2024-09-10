<?php declare(strict_types=1);

namespace Shopware\Core\Maintenance\System\Service;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Maintenance\MaintenanceException;
use Shopware\Core\Maintenance\System\Struct\DatabaseConnectionInformation;

/**
 * @deprecated tag:v6.7.0 - reason:becomes-internal
 */
#[Package('core')]
class DatabaseConnectionFactory
{
    /**
     * non-static implementation of createConnection(), can be mocked in tests
     */
    public function getConnection(DatabaseConnectionInformation $connectionInformation, bool $withoutDatabase = false): Connection
    {
        return self::createConnection($connectionInformation, $withoutDatabase);
    }

    public static function createConnection(DatabaseConnectionInformation $connectionInformation, bool $withoutDatabase = false): Connection
    {
        $connection = DriverManager::getConnection($connectionInformation->toDBALParameters($withoutDatabase), new Configuration());

        self::checkVersion($connection);

        return $connection;
    }

    private static function checkVersion(Connection $connection): void
    {
        // https://developer.shopware.com/docs/guides/installation/requirements.html#sql
        $mysqlRequiredVersion = '8.0.17';
        $mariaDBRequiredVersion = '10.11';

        $version = $connection->fetchOne('SELECT VERSION()');
        if (!\is_string($version)) {
            throw MaintenanceException::dbVersionSelectFailed();
        }
        if (\mb_stripos($version, 'mariadb') !== false) {
            if (version_compare($version, $mariaDBRequiredVersion, '<')) {
                throw MaintenanceException::dbVersionMismatch('MariaDB', $version, $mysqlRequiredVersion, $mariaDBRequiredVersion);
            }

            return;
        }

        if (version_compare($version, $mysqlRequiredVersion, '<')) {
            throw MaintenanceException::dbVersionMismatch('MySQL', $version, $mysqlRequiredVersion, $mariaDBRequiredVersion);
        }
    }
}
