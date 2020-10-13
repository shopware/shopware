<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Cache;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Contracts\Cache\ItemInterface;

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

    public function read(EntityDefinition $definition, Criteria $criteria, Context $context): EntityCollection
    {
        if (!$context->getUseCache()) {
            return $this->decorated->read($definition, $criteria, $context);
        }

        if (\in_array($definition->getClass(), CachedEntitySearcher::BLACKLIST, true)) {
            return $this->decorated->read($definition, $criteria, $context);
        }

        if ($this->hasFilter($criteria)) {
            return $this->loadFilterResult($definition, $criteria, $context);
        }

        return $this->loadResultByIds($definition, $criteria, $context);
    }

    private function loadFilterResult(EntityDefinition $definition, Criteria $criteria, Context $context)
    {
        //generate cache key for full read result
        $key = $this->cacheKeyGenerator->getReadCriteriaCacheKey($definition, $criteria, $context);
        $item = $this->cache->getItem($key);

        //hit? return
        if ($item->isHit()) {
            return $item->get();
        }

        // load full result from storage
        $collection = $this->decorated->read($definition, clone $criteria, $context);

        // cache the full result
        $this->cacheCollection($definition, $criteria, $context, $collection);

        // cache each entity for further id access
        foreach ($collection as $entity) {
            $this->cacheEntity($definition, $context, $criteria, $entity);
        }

        $this->cache->commit();

        return $collection;
    }

    private function loadResultByIds(EntityDefinition $definition, Criteria $criteria, Context $context): EntityCollection
    {
        //generate cache key list for multi cache get
        $keys = [];
        /** @var string $id */
        foreach ($criteria->getIds() as $id) {
            $keys[] = $this->cacheKeyGenerator->getEntityContextCacheKey($id, $definition, $context, $criteria);
        }

        $items = $this->cache->getItems($keys);

        $mapped = [];
        foreach ($items as $item) {
            if (!$item->isHit()) {
                continue;
            }
            $entity = $item->get();

            if ($entity instanceof Entity) {
                $mapped[$entity->getUniqueIdentifier()] = $entity;
            } else {
                $mapped[$entity] = null;
            }
        }

        $collection = $definition->getCollectionClass();

        /* @var EntityCollection $collection */
        $collection = new $collection(array_filter($mapped));

        //check which ids are not loaded from cache
        $fallback = array_diff(array_values($criteria->getIds()), array_keys($mapped));

        if (empty($fallback)) {
            //sort collection by provided id sorting
            $collection->sortByIdArray($criteria->getIds());

            return $collection;
        }

        //clone criteria to fetch missed items
        $cloned = clone $criteria;
        $cloned->setIds($fallback);

        //load missed cache items from storage
        $persistent = $this->decorated->read($definition, $cloned, $context);

        //cache all loaded items and add to collection
        foreach ($persistent as $item) {
            $this->cacheEntity($definition, $context, $criteria, $item);
            $collection->add($item);
        }

        //check if invalid ids provided and cache them with null to prevent further storage access with invalid id calls
        /** @var string $id */
        foreach ($criteria->getIds() as $id) {
            if ($collection->has($id)) {
                continue;
            }
            $this->cacheNull($definition, $context, $id);
        }

        $this->cache->commit();

        //sort collection by provided id sorting
        $collection->sortByIdArray($criteria->getIds());

        return $collection;
    }

    private function cacheEntity(EntityDefinition $definition, Context $context, Criteria $criteria, Entity $entity): void
    {
        $key = $this->cacheKeyGenerator->getEntityContextCacheKey(
            $entity->getUniqueIdentifier(),
            $definition,
            $context,
            $criteria
        );

        /** @var ItemInterface $item */
        $item = $this->cache->getItem($key);
        $item->set($entity);

        $tags = $this->cacheKeyGenerator->getAssociatedTags($definition, $entity, $context);

        /* @var EntityDefinition $definition */
        $tags[] = 'entity_' . $definition->getEntityName();

        //add cache keys for associated data
        $item->tag($tags);

        //deferred saves are persisted with the cache->commit()
        $this->cache->saveDeferred($item);
    }

    private function cacheNull(EntityDefinition $definition, Context $context, string $id): void
    {
        $key = $this->cacheKeyGenerator->getEntityContextCacheKey(
            $id,
            $definition,
            $context
        );

        /** @var ItemInterface $item */
        $item = $this->cache->getItem($key);
        $item->set($id);
        $entityTag = $definition->getEntityName() . '.id';
        $item->tag([$key, $entityTag]);

        //deferred saves are persisted with the cache->commit()
        $this->cache->saveDeferred($item);
    }

    private function cacheCollection(EntityDefinition $definition, Criteria $criteria, Context $context, EntityCollection $entityCollection): void
    {
        $key = $this->cacheKeyGenerator->getReadCriteriaCacheKey($definition, $criteria, $context);

        /** @var ItemInterface $item */
        $item = $this->cache->getItem($key);
        $item->set($entityCollection);

        $tags = [];
        foreach ($entityCollection as $entity) {
            $tags = array_merge($tags, $this->cacheKeyGenerator->getAssociatedTags($definition, $entity, $context));
        }

        $tags = array_merge($tags, $this->cacheKeyGenerator->getSearchTags($definition, $criteria));

        //add cache keys for associated data
        $item->tag($tags);

        //deferred saves are persisted with the cache->commit()
        $this->cache->saveDeferred($item);
    }

    private function hasFilter(Criteria $criteria): bool
    {
        return $criteria->getFilters() || $criteria->getPostFilters();
    }
}
