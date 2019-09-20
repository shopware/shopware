<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Cache;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Aggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\FilterAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\Cache\CacheItem;

class CachedEntityAggregator implements EntityAggregatorInterface
{
    /**
     * @var TagAwareAdapterInterface
     */
    private $cache;

    /**
     * @var EntityAggregatorInterface
     */
    private $decorated;

    /**
     * @var EntityCacheKeyGenerator
     */
    private $cacheKeyGenerator;

    /**
     * @var bool
     */
    private $enabled;

    /**
     * @var int
     */
    private $expirationTime;

    public function __construct(
        TagAwareAdapterInterface $cache,
        EntityAggregatorInterface $decorated,
        EntityCacheKeyGenerator $cacheKeyGenerator,
        bool $enabled,
        int $expirationTime
    ) {
        $this->cache = $cache;
        $this->decorated = $decorated;
        $this->cacheKeyGenerator = $cacheKeyGenerator;
        $this->enabled = $enabled;
        $this->expirationTime = $expirationTime;
    }

    public function aggregate(EntityDefinition $definition, Criteria $criteria, Context $context): AggregationResultCollection
    {
        if (!$this->enabled) {
            return $this->decorated->aggregate($definition, $criteria, $context);
        }

        if (!$context->getUseCache()) {
            return $this->decorated->aggregate($definition, $criteria, $context);
        }

        // load all hits from cache
        $result = $this->loadFromCache($definition, $criteria, $context);

        // collect all names of aggregations to compare which are not loaded from cache
        $names = array_map(function (Aggregation $aggregation) {
            return $aggregation->getName();
        }, $criteria->getAggregations());

        //check which aggregations are not loaded from cache
        $fallback = array_diff(array_values($names), array_values($result->getKeys()));

        if (empty($fallback)) {
            return $result;
        }

        //clone criteria to only load aggregations from storage which are not loaded from cache
        $clone = clone $criteria;
        $clone->resetAggregations();

        foreach ($fallback as $name) {
            $clone->addAggregation($criteria->getAggregation($name));
        }

        //load from persistent layer
        $persistent = $this->decorated->aggregate($definition, $clone, $context);

        $this->cacheResult($definition, $context, $criteria, $persistent);
        $this->cache->commit();

        foreach ($persistent as $item) {
            $result->add($item);
        }

        return $result;
    }

    private function cacheResult(EntityDefinition $definition, Context $context, Criteria $criteria, AggregationResultCollection $aggregations): void
    {
        foreach ($criteria->getAggregations() as $aggregation) {
            $result = $this->getAggregationResult($aggregation, $aggregations);

            if (!$result) {
                continue;
            }

            $key = $this->cacheKeyGenerator->getAggregationCacheKey($aggregation, $definition, $criteria, $context);

            $tags = $this->cacheKeyGenerator->getAggregationTags($definition, $criteria, $aggregation);

            /** @var CacheItem $item */
            $item = $this->cache->getItem($key);
            $item->set($result);
            $item->tag($tags);
            $item->expiresAfter($this->expirationTime);

            //deferred saves are persisted with the cache->commit()
            $this->cache->saveDeferred($item);
        }
    }

    private function loadFromCache(EntityDefinition $definition, Criteria $criteria, Context $context): AggregationResultCollection
    {
        //create key list for all aggregations
        $keys = [];
        foreach ($criteria->getAggregations() as $aggregation) {
            $keys[] = $this->cacheKeyGenerator->getAggregationCacheKey($aggregation, $definition, $criteria, $context);
        }

        //fetch keys from cache
        $items = $this->cache->getItems($keys);

        $filtered = [];
        foreach ($items as $item) {
            $filtered[] = $item->isHit() ? $item->get() : null;
        }

        return new AggregationResultCollection(array_filter($filtered));
    }

    private function getAggregationResult(Aggregation $aggregation, AggregationResultCollection $aggregations): ?AggregationResult
    {
        if ($aggregation instanceof FilterAggregation) {
            return $this->getAggregationResult($aggregation->getAggregation(), $aggregations);
        }

        return $aggregations->get($aggregation->getName());
    }
}
