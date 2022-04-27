<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache;

use Shopware\Core\Framework\Feature;
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

    private ?string $prefix;

    public function __construct(?string $prefix = null)
    {
        $this->prefix = $prefix;
    }

    /**
     * @return \Redis|\RedisArray|\RedisCluster|RedisClusterProxy|RedisProxy
     */
    public function create(string $dsn, array $options = [])
    {
        return self::createConnection($dsn, $options, !empty($this->prefix) ? $this->prefix : null);
    }

    /**
     * @deprecated tag:v6.5.0 - use create() instead
     *
     * @return \Redis|\RedisArray|\RedisCluster|RedisClusterProxy|RedisProxy
     */
    public static function createConnection(string $dsn, array $options = [], ?string $prefix = null)
    {
        Feature::throwException('v6.5.0.0', 'RedisConnectionFactory::createConnection() is deprecated. Use RedisConnectionFactory::create() instead.');

        $configHash = md5(json_encode($options, \JSON_THROW_ON_ERROR));
        $key = $dsn . $configHash . $prefix;

        if (!isset(self::$connections[$key]) || self::$connections[$key]->isConnected() === false) {
            /** @var \Redis|\RedisArray|\RedisCluster|RedisClusterProxy|RedisProxy $redis */
            $redis = RedisAdapter::createConnection($dsn, $options);

            if ($prefix) {
                $redis->setOption(\Redis::OPT_PREFIX, $prefix);
            }

            self::$connections[$key] = $redis;
        }

        return self::$connections[$key];
    }
}
