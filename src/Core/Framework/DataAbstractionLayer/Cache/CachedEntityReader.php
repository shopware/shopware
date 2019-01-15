<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Cache;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Read\ReadCriteria;
use Shopware\Core\Framework\Version\Aggregate\VersionCommit\VersionCommitDefinition;
use Shopware\Core\Framework\Version\Aggregate\VersionCommitData\VersionCommitDataDefinition;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\Cache\CacheItem;

class CachedEntityReader implements EntityReaderInterface
{
    /**
     * @var TagAwareAdapterInterface
     */
    private $cache;

    /**
     * @var EntityReaderInterface
     */
    private $decorated;

    /**
     * @var EntityCacheKeyGenerator
     */
    private $cacheKeyGenerator;

    public function __construct(
        TagAwareAdapterInterface $cache,
        EntityReaderInterface $decorated,
        EntityCacheKeyGenerator $cacheKeyGenerator
    ) {
        $this->cache = $cache;
        $this->decorated = $decorated;
        $this->cacheKeyGenerator = $cacheKeyGenerator;
    }

    public function read(string $definition, ReadCriteria $criteria, Context $context): EntityCollection
    {
        $collection = $this->loadFromCache($definition, $criteria, $context);

        if ($collection && $collection->count() === \count($criteria->getIds())) {
            return $collection;
        }

        $loaded = $this->decorated->read($definition, $criteria, $context);

        if (\count($criteria->getAssociations()) > 0) {
            return $loaded;
        }

        /** @var Entity $entity */
        foreach ($loaded as $entity) {
            $this->cacheEntity($definition, $context, $entity);
        }

        $this->cache->commit();

        return $loaded;
    }

    private function loadFromCache(string $definition, ReadCriteria $criteria, Context $context): ?EntityCollection
    {
        if (\count($criteria->getAssociations()) > 0 || $criteria->getFilters() || $criteria->getPostFilters()) {
            return null;
        }

        if (in_array($definition, [VersionCommitDefinition::class, VersionCommitDataDefinition::class], true)) {
            return null;
        }

        /** @var EntityCollection $collection */
        /** @var string|EntityDefinition $definition */
        $collection = $definition::getCollectionClass();
        $collection = new $collection();

        $keys = array_map(function ($id) use ($definition, $context) {
            return $this->cacheKeyGenerator->getEntityContextCacheKey($id, $definition, $context);
        }, $criteria->getIds());

        $items = $this->cache->getItems($keys);

        /** @var CacheItem $item */
        foreach ($items as $item) {
            if (!$item->isHit()) {
                return null;
            }

            $collection->add($item->get());
        }

        return $collection;
    }

    private function cacheEntity(string $definition, Context $context, Entity $entity): void
    {
        $key = $this->cacheKeyGenerator->getEntityContextCacheKey($entity->getUniqueIdentifier(), $definition, $context);
        /** @var CacheItem $item */
        $item = $this->cache->getItem($key);
        $item->set($entity);
        $item->tag($key);
        $item->expiresAfter(3600);

        $tags = $this->cacheKeyGenerator->getAssociatedTags($definition, $entity, $context);

        //add cache keys for associated data
        $item->tag($tags);

        //deferred saves are persisted with the cache->commit()
        $this->cache->saveDeferred($item);
    }
}
