<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache;

use Psr\Cache\CacheItemInterface;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\Cache\CacheItem;
use Symfony\Contracts\Cache\CacheTrait;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @deprecated tag:v6.7.0 - Will be removed
 */
#[Package('core')]
class CacheDecorator implements TagAwareAdapterInterface, TagAwareCacheInterface, ResetInterface
{
    use CacheTrait;

    /**
     * @internal
     */
    public function __construct(
        private readonly TagAwareCacheInterface&TagAwareAdapterInterface $decorated,
        private readonly CacheTagCollection $collection
    ) {
    }

    /**
     * @param string $key
     */
    public function getItem($key): CacheItem
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.7.0.0')
        );

        $item = $this->decorated->getItem($key);

        $this->collection->add($this->getTags($item));

        return $item;
    }

    /**
     * @return \Generator<CacheItem>
     */
    public function getItems(array $keys = []): \Generator
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.7.0.0')
        );
        foreach ($this->decorated->getItems($keys) as $item) {
            $this->collection->add($this->getTags($item));
            yield $item;
        }
    }

    public function clear(string $prefix = ''): bool
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.7.0.0')
        );

        return $this->decorated->clear($prefix);
    }

    public function hasItem(string $key): bool
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.7.0.0')
        );

        return $this->decorated->hasItem($key);
    }

    public function deleteItem(string $key): bool
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.7.0.0')
        );

        return $this->decorated->deleteItem($key);
    }

    public function deleteItems(array $keys): bool
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.7.0.0')
        );

        return $this->decorated->deleteItems($keys);
    }

    public function save(CacheItemInterface $item): bool
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.7.0.0')
        );
        $result = $this->decorated->save($item);

        // add tags after saving to get the newly added tags
        $this->collection->add($this->getTags($item));

        return $result;
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.7.0.0')
        );
        $result = $this->decorated->saveDeferred($item);

        // add tags after saving to get the newly added tags
        $this->collection->add($this->getTags($item));

        return $result;
    }

    public function commit(): bool
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.7.0.0')
        );

        return $this->decorated->commit();
    }

    /**
     * @param array<string> $tags
     */
    public function invalidateTags(array $tags): bool
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.7.0.0')
        );

        return $this->decorated->invalidateTags($tags);
    }

    public function reset(): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.7.0.0')
        );
        if ($this->decorated instanceof ResetInterface) {
            $this->decorated->reset();
        }
    }

    /**
     * @return array<string>
     */
    private function getTags(CacheItemInterface $item): array
    {
        if (!$item instanceof CacheItem) {
            return [];
        }

        return $item->getMetadata()['tags'] ?? [];
    }
}
