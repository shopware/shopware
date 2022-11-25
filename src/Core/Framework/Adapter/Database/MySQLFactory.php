<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Database;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Connections\PrimaryReadReplicaConnection;
use Doctrine\DBAL\DriverManager;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;

/**
 * @package core
 *
 * @internal
 */
class MySQLFactory
{
    public static function create(): Connection
    {
        $url = EnvironmentHelper::getVariable('DATABASE_URL', getenv('DATABASE_URL'));
        if ($url === false) {
            $url = 'mysql://root:shopware@127.0.0.1:3306/shopware';
        }

        $replicaUrl = EnvironmentHelper::getVariable('DATABASE_REPLICA_0_URL');

        $parameters = [
            'url' => $url,
            'charset' => 'utf8mb4',
            'driverOptions' => [
                \PDO::ATTR_STRINGIFY_FETCHES => true,
                \PDO::ATTR_TIMEOUT => 5, // 5s connection timeout
            ],
        ];

        if ($replicaUrl) {
            $parameters['wrapperClass'] = PrimaryReadReplicaConnection::class;
            $parameters['primary'] = ['url' => $url];
            $parameters['replica'] = [
                ['url' => $replicaUrl],
            ];

            $i = 0;
            while ($replicaUrl = EnvironmentHelper::getVariable('DATABASE_REPLICA_' . (++$i) . '_URL')) {
                $parameters['replica'][] = ['url' => $replicaUrl];
            }
        }

        if ($sslCa = EnvironmentHelper::getVariable('DATABASE_SSL_CA')) {
            $parameters['driverOptions'][\PDO::MYSQL_ATTR_SSL_CA] = $sslCa;
        }

        if ($sslCert = EnvironmentHelper::getVariable('DATABASE_SSL_CERT')) {
            $parameters['driverOptions'][\PDO::MYSQL_ATTR_SSL_CERT] = $sslCert;
        }

        if ($sslCertKey = EnvironmentHelper::getVariable('DATABASE_SSL_KEY')) {
            $parameters['driverOptions'][\PDO::MYSQL_ATTR_SSL_KEY] = $sslCertKey;
        }

        if (EnvironmentHelper::getVariable('DATABASE_SSL_DONT_VERIFY_SERVER_CERT')) {
            $parameters['driverOptions'][\PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
        }

        return DriverManager::getConnection($parameters, new Configuration());
    }
}
