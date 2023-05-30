<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityAggregator;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Aggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\DateHistogramAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\FilterAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\AvgAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\CountAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\EntityAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\MaxAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\MinAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\RangeAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\StatsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\SumAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\Bucket;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\DateHistogramResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\AvgResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\CountResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\EntityResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\MaxResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\MinResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\RangeResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\StatsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\SumResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

#[Package('core')]
class ElasticsearchEntityAggregatorHydrator extends AbstractElasticsearchAggregationHydrator
{
    /**
     * @internal
     */
    public function __construct(private readonly DefinitionInstanceRegistry $registry)
    {
    }

    public function getDecorated(): AbstractElasticsearchAggregationHydrator
    {
        throw new DecorationPatternException(self::class);
    }

    public function hydrate(EntityDefinition $definition, Criteria $criteria, Context $context, array $result): AggregationResultCollection
    {
        if (!isset($result['aggregations'])) {
            return new AggregationResultCollection();
        }

        $aggregations = new AggregationResultCollection();

        foreach ($result['aggregations'] as $name => $aggResult) {
            $aggregation = $criteria->getAggregation($name);

            if (!$aggregation) {
                continue;
            }

            $hydration = $this->hydrateAggregation($aggregation, $aggResult, $context);
            if ($hydration) {
                $aggregations->add(
                    $hydration
                );
            }
        }

        return $aggregations;
    }

    private function hydrateAggregation(Aggregation $aggregation, array $result, Context $context): ?AggregationResult
    {
        switch (true) {
            case $aggregation instanceof StatsAggregation:
                return new StatsResult($aggregation->getName(), $result['min'], $result['max'], $result['avg'], $result['sum']);

            case $aggregation instanceof AvgAggregation:
                return new AvgResult($aggregation->getName(), $result['value']);

            case $aggregation instanceof CountAggregation:
                return new CountResult($aggregation->getName(), $result['value']);

            case $aggregation instanceof EntityAggregation:
                return $this->hydrateEntityAggregation($aggregation, $result, $context);

            case $aggregation instanceof MaxAggregation:
                return new MaxResult($aggregation->getName(), $result['value']);

            case $aggregation instanceof MinAggregation:
                return new MinResult($aggregation->getName(), $result['value']);

            case $aggregation instanceof SumAggregation:
                return new SumResult($aggregation->getName(), $result['value']);

            case $aggregation instanceof FilterAggregation:
                $nested = $aggregation->getAggregation();

                if (!$nested) {
                    throw new \RuntimeException(sprintf('Filter aggregation %s contains no nested aggregation.', $aggregation->getName()));
                }
                $nestedResult = $result;

                while (isset($nestedResult[$aggregation->getName()])) {
                    $nestedResult = $nestedResult[$aggregation->getName()];
                }

                if (isset($nestedResult[$nested->getName()])) {
                    $nestedResult = $nestedResult[$nested->getName()];
                }

                return $this->hydrateAggregation($nested, $nestedResult, $context);

            case $aggregation instanceof DateHistogramAggregation:
                return $this->hydrateDateHistogram($aggregation, $result, $context);

            case $aggregation instanceof TermsAggregation:
                return $this->hydrateTermsAggregation($aggregation, $result, $context);

            case $aggregation instanceof RangeAggregation:
                return $this->hydrateRangeAggregation($aggregation, $result);

            default:
                throw new \RuntimeException(sprintf('Provided aggregation of class %s is not supported', $aggregation::class));
        }
    }

    private function hydrateEntityAggregation(EntityAggregation $aggregation, array $result, Context $context): EntityResult
    {
        if (\array_key_exists($aggregation->getName(), $result)) {
            $result = $result[$aggregation->getName()];
        }

        $ids = array_column($result['buckets'], 'key');

        if (empty($ids)) {
            $definition = $this->registry->getByEntityName($aggregation->getEntity());
            /** @var EntityCollection<Entity> $class */
            $class = $definition->getCollectionClass();

            return new EntityResult($aggregation->getName(), new $class());
        }
        $repository = $this->registry->getRepository($aggregation->getEntity());

        $entities = $repository->search(new Criteria($ids), $context);

        return new EntityResult($aggregation->getName(), $entities->getEntities());
    }

    private function hydrateDateHistogram(DateHistogramAggregation $aggregation, array $result, Context $context): ?DateHistogramResult
    {
        if (isset($result[$aggregation->getName()])) {
            $result = $result[$aggregation->getName()];
        }

        if (!isset($result['buckets'])) {
            return null;
        }

        $buckets = [];
        foreach ($result['buckets'] as $bucket) {
            $nested = null;

            $nestedAggregation = $aggregation->getAggregation();
            if ($nestedAggregation) {
                $nested = $this->hydrateAggregation($nestedAggregation, $bucket[$nestedAggregation->getName()], $context);
            }

            $key = $bucket['key'][$aggregation->getName() . '.key'];

            $date = new \DateTime($key);

            if ($dateFormat = $aggregation->getFormat()) {
                $value = $date->format($dateFormat);
            } else {
                $value = EntityAggregator::formatDate($aggregation->getInterval(), $date);
            }

            $buckets[] = new Bucket($value, $bucket['doc_count'], $nested);
        }

        return new DateHistogramResult($aggregation->getName(), $buckets);
    }

    private function hydrateTermsAggregation(TermsAggregation $aggregation, array $result, Context $context): ?TermsResult
    {
        if ($aggregation->getSorting()) {
            return $this->hydrateSortedTermsAggregation($aggregation, $result, $context);
        }

        if (isset($result[$aggregation->getName()])) {
            $result = $result[$aggregation->getName()];
        }

        $key = $aggregation->getName() . '.key';
        if (isset($result[$key])) {
            $result = $result[$key];
        }

        if (!isset($result['buckets'])) {
            return null;
        }

        $buckets = [];
        foreach ($result['buckets'] as $bucket) {
            $nested = null;

            $nestedAggregation = $aggregation->getAggregation();
            if ($nestedAggregation) {
                $nested = $this->hydrateAggregation(
                    $nestedAggregation,
                    $bucket[$nestedAggregation->getName()],
                    $context
                );
            }

            $buckets[] = new Bucket((string) $bucket['key'], $bucket['doc_count'], $nested);
        }

        return new TermsResult($aggregation->getName(), $buckets);
    }

    /**
     * @param array<string, mixed> $result
     */
    private function hydrateRangeAggregation(RangeAggregation $aggregation, array $result): ?RangeResult
    {
        if (isset($result[$aggregation->getName()])) {
            $result = $result[$aggregation->getName()];
        }

        $key = $aggregation->getName() . '.key';
        if (isset($result[$key])) {
            $result = $result[$key];
        }

        if (!isset($result['buckets'])) {
            return null;
        }

        $ranges = [];
        foreach ($result['buckets'] as $bucket) {
            $ranges[(string) $bucket['key']] = (int) $bucket['doc_count'];
        }

        return new RangeResult($aggregation->getName(), $ranges);
    }

    private function hydrateSortedTermsAggregation(TermsAggregation $aggregation, array $result, Context $context): ?TermsResult
    {
        if (isset($result[$aggregation->getName()])) {
            $result = $result[$aggregation->getName()];
        }

        if (!isset($result['buckets'])) {
            return null;
        }

        $buckets = [];
        foreach ($result['buckets'] as $bucket) {
            $nested = null;

            $nestedAggregation = $aggregation->getAggregation();
            if ($nestedAggregation) {
                $nested = $this->hydrateAggregation(
                    $nestedAggregation,
                    $bucket[$nestedAggregation->getName()],
                    $context
                );
            }

            $key = $bucket['key'][$aggregation->getName() . '.key'];

            $buckets[] = new Bucket((string) $key, $bucket['doc_count'], $nested);
        }

        return new TermsResult($aggregation->getName(), $buckets);
    }
}
