<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Database;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Connections\PrimaryReadReplicaConnection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Pdo\Mysql;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Config\Util\XmlUtils;

/**
 * @phpstan-import-type Params from DriverManager
 *
 * @internal
 */
#[Package('framework')]
class MySQLFactory
{
    public const PLACEHOLDER_DATABASE_URL = 'mysql://_placeholder.test';

    /**
     * Returns true, when bin/ci is used and Shopware is called in a CI/CD environment where the Database is not available to warmup caches
     */
    public static function hasNoDatabaseAvailable(): bool
    {
        return (string) EnvironmentHelper::getVariable('DATABASE_URL', '') === self::PLACEHOLDER_DATABASE_URL;
    }

    /**
     * @param array<Middleware> $middlewares
     */
    public static function create(array $middlewares = []): Connection
    {
        $config = (new Configuration())
            ->setMiddlewares($middlewares);

        $parameters = self::getConnectionParameters();
        $disableMultiStatements = Feature::isActive('DISABLE_SQL_MULTI_STATEMENTS');

        // The first connection is created before the feature registry can resolve the parent major.
        if (Feature::getRegisteredFeatures() === []
            && !EnvironmentHelper::hasVariable('DISABLE_SQL_MULTI_STATEMENTS')
            && !EnvironmentHelper::hasVariable('disable_sql_multi_statements')
        ) {
            $disableMultiStatements = $disableMultiStatements || Feature::isActive('v6.8.0.0');
        }

        if ($disableMultiStatements) {
            $parameters['driverOptions'][Mysql::ATTR_MULTI_STATEMENTS] = false;
        }

        $parameters = self::addReplicaParameters($parameters);

        return DriverManager::getConnection($parameters, $config);
    }

    public static function createMigrationConnection(Connection $connection): Connection
    {
        $parameters = self::getConnectionParameters();
        unset($parameters['primary'], $parameters['replica'], $parameters['keepReplica']);

        if (is_a($parameters['wrapperClass'] ?? Connection::class, PrimaryReadReplicaConnection::class, true)) {
            unset($parameters['wrapperClass']);
        }

        $parameters['driverOptions'][Mysql::ATTR_MULTI_STATEMENTS] = true;
        // Persistent PDO handles can be reused across connections with different driver options.
        $parameters['driverOptions'][\PDO::ATTR_PERSISTENT] = false;
        $parameters['persistent'] = false;

        return DriverManager::getConnection($parameters, $connection->getConfiguration());
    }

    /**
     * @return Params&array{charset: string, driverOptions: array<mixed>, ...}
     */
    private static function getConnectionParameters(): array
    {
        $url = (string) EnvironmentHelper::getVariable('DATABASE_URL', getenv('DATABASE_URL'));
        if ($url === '') {
            $url = 'mysql://root:shopware@127.0.0.1:3306/shopware';
        }

        $dsnParser = new DsnParser(['mysql' => 'pdo_mysql']);
        $dsnParameters = self::parseDsn($dsnParser, $url);

        $parameters = array_merge([
            'charset' => 'utf8mb4',
            'driver' => 'pdo_mysql',
        ], $dsnParameters); // adding parameters that are not in the DSN

        // Merge driverOptions separately using + to preserve PDO constant keys
        $parameters['driverOptions'] = [
            \PDO::ATTR_STRINGIFY_FETCHES => true,
            \PDO::ATTR_TIMEOUT => 5,
        ] + ($dsnParameters['driverOptions'] ?? []);

        $parameters['driverOptions'][Mysql::ATTR_INIT_COMMAND] = 'SET
            @@session.time_zone = \'+00:00\',
            @@session.group_concat_max_len = CAST(IF(@@group_concat_max_len > 320000, @@group_concat_max_len, 320000) AS UNSIGNED),
            @@session.sql_mode = (SELECT REPLACE(@@sql_mode, \'ONLY_FULL_GROUP_BY\', \'\'))';

        if ($sslCa = EnvironmentHelper::getVariable('DATABASE_SSL_CA')) {
            $parameters['driverOptions'][Mysql::ATTR_SSL_CA] = $sslCa;
        }

        if ($sslCert = EnvironmentHelper::getVariable('DATABASE_SSL_CERT')) {
            $parameters['driverOptions'][Mysql::ATTR_SSL_CERT] = $sslCert;
        }

        if ($sslCertKey = EnvironmentHelper::getVariable('DATABASE_SSL_KEY')) {
            $parameters['driverOptions'][Mysql::ATTR_SSL_KEY] = $sslCertKey;
        }

        if (EnvironmentHelper::getVariable('DATABASE_SSL_DONT_VERIFY_SERVER_CERT') && \defined('\Pdo\Mysql::ATTR_SSL_VERIFY_SERVER_CERT')) {
            $parameters['driverOptions'][Mysql::ATTR_SSL_VERIFY_SERVER_CERT] = false;
        }

        if (EnvironmentHelper::getVariable('DATABASE_PERSISTENT_CONNECTION')) {
            $parameters['driverOptions'][\PDO::ATTR_PERSISTENT] = true;
        }

        if (EnvironmentHelper::getVariable('DATABASE_PROTOCOL_COMPRESSION')) {
            $parameters['driverOptions'][Mysql::ATTR_COMPRESS] = true;
        }

        return $parameters;
    }

    /**
     * Only application connections use replicas; migrations must immediately read their own writes and schema changes on the primary.
     *
     * @param Params&array{charset: string, driverOptions: array<mixed>, ...} $parameters
     *
     * @return Params
     */
    private static function addReplicaParameters(array $parameters): array
    {
        $replicaUrl = (string) EnvironmentHelper::getVariable('DATABASE_REPLICA_0_URL');
        if ($replicaUrl !== '') {
            if (!isset($parameters['wrapperClass'])) {
                $parameters['wrapperClass'] = PrimaryReadReplicaConnection::class;
            }

            // Keep the replica connection distinct from the primary one, so
            // ReplicaConnectionResetter can switch back to the replica between
            // requests. Without this option DBAL aliases the replica to the
            // primary once the primary was used, which pins long running
            // workers to the primary for their whole lifetime.
            // See https://symfony.com/doc/current/doctrine/dbal.html#using-primary-replica-connections-read-replicas
            if (!\array_key_exists('keepReplica', $parameters)) {
                $parameters['keepReplica'] = true;
            } else {
                $parameters['keepReplica'] = (bool) self::castValue($parameters['keepReplica']);
            }

            $parameters['primary'] = $parameters;
            unset($parameters['primary']['wrapperClass'], $parameters['primary']['primary'], $parameters['primary']['replica'], $parameters['primary']['keepReplica']);

            $parameters['replica'] = [];
            $dsnParser = new DsnParser(['mysql' => 'pdo_mysql']);

            for ($i = 0; $replicaUrl = (string) EnvironmentHelper::getVariable('DATABASE_REPLICA_' . $i . '_URL'); ++$i) {
                $replicaParams = self::parseDsn($dsnParser, $replicaUrl);
                unset($replicaParams['primary'], $replicaParams['replica'], $replicaParams['keepReplica']);

                $parameters['replica'][$i] = array_merge([
                    'charset' => $parameters['charset'],
                ], $replicaParams);
                $parameters['replica'][$i]['driverOptions'] = $parameters['driverOptions'] + ($replicaParams['driverOptions'] ?? []);
            }
        }

        return $parameters;
    }

    /**
     * @return Params
     */
    private static function parseDsn(DsnParser $dsnParser, string $url): array
    {
        $dsnParameters = $dsnParser->parse($url);

        $dsnParameters['driverOptions'] = array_map(static function (mixed $value): mixed {
            return self::castValue($value);
        }, $dsnParameters['driverOptions'] ?? []);

        return $dsnParameters;
    }

    private static function castValue(mixed $value): mixed
    {
        if (is_iterable($value)) {
            foreach ($value as &$item) {
                $item = self::castValue($item);
            }

            return $value;
        }

        if (!\is_string($value)) {
            return $value;
        }

        return XmlUtils::phpize($value);
    }
}
