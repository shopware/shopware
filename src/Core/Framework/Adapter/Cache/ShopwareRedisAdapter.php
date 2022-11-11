<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache;

use Symfony\Component\Cache\Adapter\RedisTagAwareAdapter;
use Symfony\Component\Cache\Marshaller\MarshallerInterface;

/**
 * @package core
 */
class ShopwareRedisAdapter extends RedisTagAwareAdapter
{
    public function __construct($redis, string $namespace = '', int $defaultLifetime = 0, ?MarshallerInterface $marshaller = null, ?string $prefix = null)
    {
        parent::__construct($redis, $prefix . $namespace, $defaultLifetime, $marshaller);
    }
}
