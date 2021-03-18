<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache;

use Psr\Cache\CacheItemInterface;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\Cache\CacheItem;

class CacheDecorator implements TagAwareAdapterInterface
{
    private TagAwareAdapterInterface $decorated;

    private CacheTagCollection $collection;

    private \ReflectionProperty $property;

    public function __construct(TagAwareAdapterInterface $decorated, CacheTagCollection $collection)
    {
        $this->decorated = $decorated;
        $this->collection = $collection;

        // hack to get access to tags in save() - https://github.com/symfony/symfony/issues/36697
        $this->property = (new \ReflectionClass(CacheItem::class))->getProperty('newMetadata');
        $this->property->setAccessible(true);
    }

    /**
     * @param string $key
     *
     * @return CacheItem
     */
    public function getItem($key)
    {
        $item = $this->decorated->getItem($key);

        $this->collection->add($this->getTags($item));

        return $item;
    }

    /**
     * @return \Generator<CacheItem>
     */
    public function getItems(array $keys = [])
    {
        foreach ($this->decorated->getItems($keys) as $item) {
            $this->collection->add($this->getTags($item));
            yield $item;
        }
    }

    /**
     * @return bool
     */
    public function clear(string $prefix = '')
    {
        return $this->decorated->clear($prefix);
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function hasItem($key)
    {
        return $this->decorated->hasItem($key);
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function deleteItem($key)
    {
        return $this->decorated->deleteItem($key);
    }

    /**
     * @param string[] $keys
     *
     * @return bool
     */
    public function deleteItems(array $keys)
    {
        return $this->decorated->deleteItems($keys);
    }

    /**
     * @return bool
     */
    public function save(CacheItemInterface $item)
    {
        $this->collection->add($this->getTags($item));

        return $this->decorated->save($item);
    }

    /**
     * @return bool
     */
    public function saveDeferred(CacheItemInterface $item)
    {
        $this->collection->add($this->getTags($item));

        return $this->decorated->saveDeferred($item);
    }

    /**
     * @return bool
     */
    public function commit()
    {
        return $this->decorated->commit();
    }

    /**
     * @param string[] $tags
     *
     * @return bool
     */
    public function invalidateTags(array $tags)
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
