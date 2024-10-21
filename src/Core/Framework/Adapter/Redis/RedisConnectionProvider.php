<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Redis;

use Psr\Container\ContainerInterface;
use Shopware\Core\Framework\Adapter\AdapterException;
use Shopware\Core\Framework\Adapter\Cache\RedisConnectionFactory;
use Shopware\Core\Framework\Log\Package;

/**
 * RedisConnection corresponds to a return type of symfony's RedisAdapter::createConnection and may change with symfony update.
 *
 * @phpstan-type RedisConnection \Redis|\RedisArray|\RedisCluster|\Predis\ClientInterface|\Relay\Relay
 */
#[Package('core')]
class RedisConnectionProvider
{
    /**
     * @internal
     */
    public function __construct(
        private ContainerInterface $serviceLocator,

        /**
         * @deprecated tag:v6.7.0 - Remove in 6.7
         */
        private RedisConnectionFactory $redisConnectionFactory,
    ) {
    }

    /**
     * @return RedisConnection
     */
    public function getConnection(string $connectionName)
    {
        if (!$this->hasConnection($connectionName)) {
            throw AdapterException::unknownRedisConnection($connectionName);
        }

        return $this->serviceLocator->get($this->getServiceName($connectionName));
    }

    public function hasConnection(string $connectionName): bool
    {
        return $this->serviceLocator->has($this->getServiceName($connectionName));
    }

    /**
     * @internal
     *
     * @deprecated tag:v6.7.0 reason:factory-for-deprecation - Will be replaced by getConnection, as only named based connection will be supported - Remove in 6.7
     */
    public function getOrCreateFromDsn(?string $connectionName, ?string $dsn): object
    {
        if ($connectionName === null && $dsn === null) {
            throw AdapterException::missingRedisConnectionParameter($connectionName, $dsn);
        }

        if ($connectionName !== null) {
            return $this->getConnection($connectionName);
        }

        return $this->redisConnectionFactory->create($dsn);
    }

    private function getServiceName(string $connectionName): string
    {
        return 'shopware.redis.connection.' . $connectionName;
    }
}
