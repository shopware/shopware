<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache;

use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Traits\RedisClusterProxy;
use Symfony\Component\Cache\Traits\RedisProxy;

/**
 * Used to create new Redis connection based on a connection dsn.
 * Existing connections are reused if there are any.
 */
class RedisConnectionFactory
{
    /**
     * This static variable is not reset on purpose, as we may reuse existing redis connections over multiple requests
     */
    private static array $connections = [];

    /**
     * @return \Redis|\RedisArray|\RedisCluster|RedisClusterProxy|RedisProxy
     */
    public static function createConnection(string $dsn, array $options = [])
    {
        $configHash = md5(json_encode($options, \JSON_THROW_ON_ERROR));
        $key = $dsn . $configHash;

        if (!isset(self::$connections[$key]) || self::$connections[$key]->isConnected() === false) {
            self::$connections[$key] = RedisAdapter::createConnection($dsn, $options);
        }

        return self::$connections[$key];
    }
}
