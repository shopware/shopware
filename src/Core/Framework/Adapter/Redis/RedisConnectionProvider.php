<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Redis;

use Psr\Container\ContainerInterface;
use Shopware\Core\Framework\Adapter\AdapterException;
use Shopware\Core\Framework\Log\Package;

/**
 * RedisConnection corresponds to a return type of symfony's RedisAdapter::createConnection and may change with symfony update.
 *
 * @phpstan-type RedisConnection \Redis|\RedisArray|\RedisCluster|\Predis\ClientInterface|\Relay\Relay
 */
#[Package('framework')]
class RedisConnectionProvider
{
    /**
     * @internal
     */
    public function __construct(
        private ContainerInterface $serviceLocator,
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

    private function getServiceName(string $connectionName): string
    {
        return 'shopware.redis.connection.' . $connectionName;
    }
}
