<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Cache\Adapter\RedisAdapter;

/**
 * Used to create new Redis connection based on a connection dsn.
 * Existing connections are reused if there are any.
 *
 * @final
 */
#[Package('core')]
class RedisConnectionFactory
{
    /**
     * This static variable is not reset on purpose, as we may reuse existing redis connections over multiple requests
     *
     * @var array<string, \Redis|\RedisArray|\RedisCluster|\Predis\ClientInterface|\Relay\Relay>
     */
    private static array $connections = [];

    /**
     * @internal
     */
    public function __construct(private readonly ?string $prefix = null)
    {
    }

    /**
     * @param array<string, mixed> $options
     * Don't type hint the native return types, as symfony might change them in the future
     *
     * @return \Redis|\RedisArray|\RedisCluster|\Predis\ClientInterface|\Relay\Relay
     */
    public function create(string $dsn, array $options = [])
    {
        $configHash = md5(json_encode($options, \JSON_THROW_ON_ERROR));
        $key = $dsn . $configHash . $this->prefix;

        if (!isset(self::$connections[$key]) || (
            \method_exists(self::$connections[$key], 'isConnected') && self::$connections[$key]->isConnected() === false
        )) {
            /** @var \Redis|\RedisArray|\RedisCluster|\Predis\ClientInterface|\Relay\Relay $redis */
            $redis = RedisAdapter::createConnection($dsn, $options);

            if ($this->prefix && \method_exists($redis, 'setOption')) {
                $redis->setOption(\Redis::OPT_PREFIX, $this->prefix);
            }

            self::$connections[$key] = $redis;
        }

        return self::$connections[$key];
    }
}
