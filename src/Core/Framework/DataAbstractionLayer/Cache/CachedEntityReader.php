<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Cache;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
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

    public function read(string $definition, Criteria $criteria, Context $context): EntityCollection
    {
        $collection = $this->loadFromCache($definition, $criteria, $context);

        if ($collection) {
            return $collection;
        }

        $loaded = $this->decorated->read($definition, $criteria, $context);

        /** @var Entity $entity */
        foreach ($loaded as $entity) {
            $this->cacheEntity($definition, $context, $criteria, $entity);
        }

        //If ids inside criteria are filtered or not found cache null result to prevent uncachable querys
        foreach ($criteria->getIds() as $id) {
            if (!$loaded->has($id)) {
                $this->cacheNull($definition, $context, $id);
            }
        }

        if ($criteria->getFilters() || $criteria->getPostFilters()) {
            $this->cacheCollection($definition, $criteria, $context, $loaded);
        }

        $this->cache->commit();

        return $loaded;
    }

    private function loadFromCache(string $definition, Criteria $criteria, Context $context): ?EntityCollection
    {
        if (in_array($definition, [VersionCommitDefinition::class, VersionCommitDataDefinition::class], true)) {
            return null;
        }

        //contains filter? then fetch whole result
        if ($criteria->getFilters() || $criteria->getPostFilters()) {
            $key = $this->cacheKeyGenerator->getReadCriteriaCacheKey($definition, $criteria, $context);
            $item = $this->cache->getItem($key);
            if (!$item->isHit()) {
                return null;
            }

            return $item->get();
        }

        $keys = array_map(function ($id) use ($definition, $criteria, $context) {
            return $this->cacheKeyGenerator->getEntityContextCacheKey($id, $definition, $context, $criteria);
        }, $criteria->getIds());

        $items = $this->cache->getItems($keys);

        /** @var EntityCollection $collection */
        /** @var string|EntityDefinition $definition */
        $collection = $definition::getCollectionClass();
        $collection = new $collection();

        /** @var CacheItem $item */
        foreach ($items as $item) {
            //if item handle the id as "resolved" for cache request
            if ($item->isHit() && $item->get() === null) {
                continue;
            }
            if (!$item->isHit()) {
                return null;
            }
            $collection->add($item->get());
        }

        return $collection;
    }

    private function cacheEntity(string $definition, Context $context, Criteria $criteria, Entity $entity): void
    {
        $key = $this->cacheKeyGenerator->getEntityContextCacheKey(
            $entity->getUniqueIdentifier(), $definition, $context, $criteria
        );
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

    private function cacheNull(string $definition, Context $context, string $id): void
    {
        $key = $this->cacheKeyGenerator->getEntityContextCacheKey(
            $id, $definition, $context
        );
        /** @var CacheItem $item */
        $item = $this->cache->getItem($key);

        $item->set(null);
        $item->tag($key);
        $item->expiresAfter(3600);

        //deferred saves are persisted with the cache->commit()
        $this->cache->saveDeferred($item);
    }

    private function cacheCollection(string $definition, Criteria $criteria, Context $context, EntityCollection $entityCollection): void
    {
        $key = $this->cacheKeyGenerator->getReadCriteriaCacheKey($definition, $criteria, $context);

        /** @var CacheItem $item */
        $item = $this->cache->getItem($key);
        $item->set($entityCollection);
        $item->tag($key);
        $item->expiresAfter(3600);

        $tags = [];
        foreach ($entityCollection as $entity) {
            $tags = array_merge($tags, $this->cacheKeyGenerator->getAssociatedTags($definition, $entity, $context));
        }

        $fieldTags = $this->cacheKeyGenerator->getSearchTags($definition, $criteria);
        $tags = array_merge($tags, $fieldTags);

        //add cache keys for associated data
        $item->tag(array_keys(array_flip($tags)));

        //deferred saves are persisted with the cache->commit()
        $this->cache->saveDeferred($item);
    }
}
