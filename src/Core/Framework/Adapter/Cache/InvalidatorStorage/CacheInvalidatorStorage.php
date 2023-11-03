<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache\InvalidatorStorage;

use Shopware\Core\Framework\Adapter\Cache\CacheCompressor;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Cache\Adapter\AdapterInterface;

/**
 * @deprecated tag:v6.6.0 - This storage is unsafe for multi-server use, please use the RedisInvalidatorStorage instead
 */
#[Package('core')]
class CacheInvalidatorStorage extends AbstractInvalidatorStorage
{
    private const CACHE_KEY = 'invalidation';

    /**
     * @internal
     */
    public function __construct(
        private readonly AdapterInterface $cache
    ) {
    }

    public function store(array $tags): void
    {
        Feature::triggerDeprecationOrThrow('v6.6.0.0', 'The CacheInvalidatorStorage is unsafe for multi-server use, please use the RedisInvalidatorStorage instead');

        $item = $this->cache->getItem(self::CACHE_KEY);

        $values = CacheCompressor::uncompress($item) ?? [];

        foreach ($tags as $tag) {
            $values[$tag] = '1';
        }

        $item = CacheCompressor::compress($item, $values);

        $this->cache->save($item);
    }

    public function loadAndDelete(): array
    {
        Feature::triggerDeprecationOrThrow('v6.6.0.0', 'The CacheInvalidatorStorage is unsafe for multi-server use, please use the RedisInvalidatorStorage instead');

        $item = $this->cache->getItem(self::CACHE_KEY);

        /** @var array<string, int> $values */
        $values = CacheCompressor::uncompress($item) ?? [];

        $this->cache->deleteItem(self::CACHE_KEY);

        return array_keys($values);
    }
}
