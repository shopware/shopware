<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache;

use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Marshaller\MarshallerInterface;

/**
 * @package core
 *
 * @internal
 */
class ShopwareRedisAdapter extends RedisAdapter
{
    public function __construct($redis, string $namespace = '', int $defaultLifetime = 0, ?MarshallerInterface $marshaller = null, ?string $prefix = null)
    {
        parent::__construct($redis, $prefix . $namespace, $defaultLifetime, $marshaller);
    }
}
