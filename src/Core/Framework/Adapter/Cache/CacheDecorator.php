<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache;

use Psr\Cache\CacheItemInterface;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\Cache\CacheItem;
use Symfony\Contracts\Cache\CacheTrait;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class CacheDecorator implements TagAwareAdapterInterface, TagAwareCacheInterface
{
    use CacheTrait;

    /**
     * @var TagAwareCacheInterface&TagAwareAdapterInterface
     */
    private $decorated;

    private CacheTagCollection $collection;

    private \ReflectionProperty $property;

    /**
     * @internal
     *
     * @param TagAwareCacheInterface&TagAwareAdapterInterface $decorated
     */
    public function __construct($decorated, CacheTagCollection $collection)
    {
        $this->decorated = $decorated;
        $this->collection = $collection;

        // hack to get access to tags in save() - https://github.com/symfony/symfony/issues/36697
        $this->property = (new \ReflectionClass(CacheItem::class))->getProperty('newMetadata');
        $this->property->setAccessible(true);
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
        $this->collection->add($this->getTags($item));

        return $this->decorated->save($item);
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        $this->collection->add($this->getTags($item));

        return $this->decorated->saveDeferred($item);
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

    private function getTags(CacheItemInterface $item): array
    {
        if (!$item instanceof CacheItem) {
            return [];
        }
        $metaData = $item->getMetadata();

        $new = $this->property->getValue($item);

        return array_merge(
            $metaData['tags'] ?? [],
            $new['tags'] ?? []
        );
    }
}
