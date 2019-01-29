<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Cache;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Aggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\AggregationResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregatorResult;
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

    public function __construct(
        TagAwareAdapterInterface $cache,
        EntityAggregatorInterface $decorated,
        EntityCacheKeyGenerator $cacheKeyGenerator
    ) {
        $this->cache = $cache;
        $this->decorated = $decorated;
        $this->cacheKeyGenerator = $cacheKeyGenerator;
    }

    public function aggregate(string $definition, Criteria $criteria, Context $context): AggregatorResult
    {
        // load all hits from cache
        $result = $this->loadFromCache($definition, $criteria, $context);

        // collect all names of aggregations to compare which are not loaded from cache
        $names = array_map(function (Aggregation $aggregation) {
            return $aggregation->getName();
        }, $criteria->getAggregations());

        //check which aggregations are not loaded from cache
        $fallback = array_diff(array_values($names), array_values($result->getKeys()));

        if (empty($fallback)) {
            return new AggregatorResult($result, $context, $criteria);
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

        foreach ($persistent->getAggregations() as $item) {
            $result->add($item);
        }

        return new AggregatorResult($result, $context, $criteria);
    }

    private function cacheResult(string $definition, Context $context, Criteria $criteria, AggregatorResult $result): void
    {
        /** @var AggregationResult $aggregationResult */
        foreach ($result->getAggregations() as $aggregationResult) {
            $key = $this->cacheKeyGenerator->getAggregationCacheKey($aggregationResult->getAggregation(), $definition, $criteria, $context);

            /** @var CacheItem $item */
            $item = $this->cache->getItem($key);
            $item->set($aggregationResult);
            $item->tag($key);
            $item->expiresAfter(3600);

            //deferred saves are persisted with the cache->commit()
            $this->cache->saveDeferred($item);
        }
    }

    private function loadFromCache(string $definition, Criteria $criteria, Context $context): AggregationResultCollection
    {
        //create key list for all aggregations
        $keys = [];
        foreach ($criteria->getAggregations() as $aggregation) {
            $keys[] = $this->cacheKeyGenerator->getAggregationCacheKey($aggregation, $definition, $criteria, $context);
        }

        //fetch keys from cache
        $items = $this->cache->getItems($keys);
        $items = iterator_to_array($items);

        //filter only hit items
        $items = array_filter($items, function (CacheItem $item) {
            return $item->isHit();
        });

        //convert cache items to aggregation results
        $items = array_map(function (CacheItem $item) {
            return $item->get();
        }, $items);

        return new AggregationResultCollection($items);
    }
}
