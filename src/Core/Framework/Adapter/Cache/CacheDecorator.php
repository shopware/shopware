<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache;

use Psr\Cache\CacheItemInterface;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\Cache\CacheItem;
use Symfony\Contracts\Cache\CacheTrait;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

#[Package('core')]
class CacheDecorator implements TagAwareAdapterInterface, TagAwareCacheInterface
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
        $item = $this->decorated->getItem($key);

        $this->collection->add($this->getTags($item));

        return $item;
    }

    /**
     * @return \Generator<CacheItem>
     */
    public function getItems(array $keys = []): \Generator
    {
        foreach ($this->decorated->getItems($keys) as $item) {
            $this->collection->add($this->getTags($item));
            yield $item;
        }
    }

    public function clear(string $prefix = ''): bool
    {
        return $this->decorated->clear($prefix);
    }

    public function hasItem(string $key): bool
    {
        return $this->decorated->hasItem($key);
    }

    public function deleteItem(string $key): bool
    {
        return $this->decorated->deleteItem($key);
    }

    public function deleteItems(array $keys): bool
    {
        return $this->decorated->deleteItems($keys);
    }

    public function save(CacheItemInterface $item): bool
    {
        $result = $this->decorated->save($item);

        // add tags after saving to get the newly added tags
        $this->collection->add($this->getTags($item));

        return $result;
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        $result = $this->decorated->saveDeferred($item);

        // add tags after saving to get the newly added tags
        $this->collection->add($this->getTags($item));

        return $result;
    }

    public function commit(): bool
    {
        return $this->decorated->commit();
    }

    /**
     * @param array<string> $tags
     */
    public function invalidateTags(array $tags): bool
    {
        return $this->decorated->invalidateTags($tags);
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
