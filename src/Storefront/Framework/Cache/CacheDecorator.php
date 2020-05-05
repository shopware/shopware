<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache;

use Psr\Cache\CacheItemInterface;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\Cache\CacheItem;

class CacheDecorator implements TagAwareAdapterInterface
{
    /**
     * @var TagAwareAdapterInterface
     */
    private $decorated;

    /**
     * @var CacheTagCollection
     */
    private $collection;

    /**
     * @var \ReflectionProperty
     */
    private $property;

    public function __construct(TagAwareAdapterInterface $decorated, CacheTagCollection $collection)
    {
        $this->decorated = $decorated;
        $this->collection = $collection;

        // hack to get access to tags in save() - https://github.com/symfony/symfony/issues/36697
        $class = new \ReflectionClass(CacheItem::class);
        $this->property = $class->getProperty('newMetadata');
        $this->property->setAccessible(true);
    }

    public function getItem($key)
    {
        $item = $this->decorated->getItem($key);

        $this->collection->add($this->getTags($item));

        return $item;
    }

    public function getItems(array $keys = [])
    {
        $items = $this->decorated->getItems($keys);

        $items = iterator_to_array($items);
        foreach ($items as $item) {
            $this->collection->add($this->getTags($item));
        }

        return $items;
    }

    public function clear(string $prefix = '')
    {
        return $this->decorated->clear($prefix);
    }

    public function hasItem($key)
    {
        return $this->decorated->hasItem($key);
    }

    public function deleteItem($key)
    {
        return $this->decorated->deleteItem($key);
    }

    public function deleteItems(array $keys)
    {
        return $this->decorated->deleteItems($keys);
    }

    public function save(CacheItemInterface $item)
    {
        $this->collection->add($this->getTags($item));

        return $this->decorated->save($item);
    }

    public function saveDeferred(CacheItemInterface $item)
    {
        $this->collection->add($this->getTags($item));

        return $this->decorated->saveDeferred($item);
    }

    public function commit()
    {
        return $this->decorated->commit();
    }

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
