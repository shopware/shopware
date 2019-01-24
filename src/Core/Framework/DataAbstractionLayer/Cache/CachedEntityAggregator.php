<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Cache;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregatorResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Shopware\Core\Framework\Version\Aggregate\VersionCommit\VersionCommitDefinition;
use Shopware\Core\Framework\Version\Aggregate\VersionCommitData\VersionCommitDataDefinition;
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

    public function aggregate(string $entityDefinition, Criteria $criteria, Context $context): AggregatorResult
    {
        $collection = $this->loadFromCache($entityDefinition, $criteria, $context);

        if ($collection) {
            return $collection;
        }

        $aggregatorResult = $this->loadFromPersistent($entityDefinition, $criteria, $context);

        $this->cacheResult($entityDefinition, $context, $criteria, $aggregatorResult);

        $this->cache->commit();

        return $aggregatorResult;
    }

    private function loadFromCache(string $entityDefinition, Criteria $criteria, Context $context): ?AggregatorResult
    {
        if (in_array($entityDefinition, [VersionCommitDefinition::class, VersionCommitDataDefinition::class], true)) {
            return null;
        }

        $keys = $this->cacheKeyGenerator->getAggregatorResultContextCacheKeys($entityDefinition, $criteria, $context);

        $result = new AggregatorResult(new AggregationResultCollection(), $context, $criteria);

        $items = iterator_to_array($this->cache->getItems($keys));

        //Any Hits?
        if (!$this->isCacheItemsWithHits($items)) {
            return null;
        }

        $modifiedCriteria = clone $criteria;
        $modifiedCriteria->resetAggregations();

        /** @var CacheItem $item */
        foreach ($items as $item) {
            if (!$item->isHit()) {
                $modifiedCriteria = $this->addMissedAggregation($criteria, $item, $modifiedCriteria);
                continue;
            }
            $result->getAggregations()->add($item->get());
        }

        $result = $this->readMissedAggregations($entityDefinition, $context, $modifiedCriteria, $result);

        return $result;
    }

    private function cacheResult(
        string $entityDefinition, Context $context, Criteria $criteria, AggregatorResult $result
    ): void {
        foreach ($result->getAggregations() as $aggregation) {
            $key = $this->cacheKeyGenerator->getAggregatorResultContextCacheKey(
                $aggregation->getName(), $entityDefinition, $criteria, $context
            );
            /** @var CacheItem $item */
            $item = $this->cache->getItem($key);
            $item->set($aggregation);
            $item->tag($key);
            $item->expiresAfter(3600);

            //deferred saves are persisted with the cache->commit()
            $this->cache->saveDeferred($item);
        }
    }

    private function isCacheItemsWithHits($items): bool
    {
        /** @var CacheItem $item */
        foreach ($items as $item) {
            if ($item->isHit()) {
                return true;
            }
        }

        return false;
    }

    private function addMissedAggregation(Criteria $criteria, CacheItem $item, Criteria $modifiedCriteria): Criteria
    {
        $missedAggregation = $criteria->getAggregationByName(
            $this->cacheKeyGenerator->getCacheKeyAggregationName($item->getKey())
        );

        if ($missedAggregation) {
            $modifiedCriteria->addAggregation($missedAggregation);
        }

        return $modifiedCriteria;
    }

    private function readMissedAggregations(
        string $entityDefinition, Context $context, Criteria $modifiedCriteria, AggregatorResult $result
    ): AggregatorResult {
        if (\count($modifiedCriteria->getAggregations()) > 0) {
            $missingAggregations = $this->loadFromPersistent($entityDefinition, $modifiedCriteria, $context);
            foreach ($missingAggregations->getAggregations() as $aggregation) {
                $result->getAggregations()->add($aggregation);
            }
            $this->cacheResult($entityDefinition, $context, $modifiedCriteria, $missingAggregations);
        }

        return $result;
    }

    private function loadFromPersistent(string $entityDefinition, Criteria $criteria, Context $context): AggregatorResult
    {
        $aggregatorResult = $this->decorated->aggregate($entityDefinition, $criteria, $context);

        return $aggregatorResult;
    }
}
