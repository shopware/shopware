<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\DataAbstractionLayer;

use Elasticsearch\Client;
use ONGR\ElasticsearchDSL\Search;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityAggregator;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
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
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\StatsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\SumResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;

class ElasticsearchEntityAggregator implements EntityAggregatorInterface
{
    /**
     * @var ElasticsearchHelper
     */
    private $helper;

    /**
     * @var Client
     */
    private $client;

    /**
     * @var EntityAggregatorInterface
     */
    private $decorated;

    /**
     * @var DefinitionInstanceRegistry
     */
    private $definitionInstanceRegistry;

    public function __construct(
        ElasticsearchHelper $helper,
        Client $client,
        EntityAggregatorInterface $decorated,
        DefinitionInstanceRegistry $definitionInstanceRegistry
    ) {
        $this->helper = $helper;
        $this->client = $client;
        $this->decorated = $decorated;
        $this->definitionInstanceRegistry = $definitionInstanceRegistry;
    }

    public function aggregate(EntityDefinition $definition, Criteria $criteria, Context $context): AggregationResultCollection
    {
        if (!$this->helper->allowSearch($definition, $context)) {
            return $this->decorated->aggregate($definition, $criteria, $context);
        }

        $search = new Search();
        $this->helper->addFilters($definition, $criteria, $search, $context);
        $this->helper->addQueries($definition, $criteria, $search, $context);
        $this->helper->addAggregations($definition, $criteria, $search, $context);

        $search->setSize(0);

        try {
            $result = $this->client->search([
                'index' => $this->helper->getIndexName($definition, $context->getLanguageId()),
                'type' => $definition->getEntityName(),
                'body' => $search->toArray(),
            ]);
        } catch (\Throwable $e) {
            $this->helper->logOrThrowException($e);

            return $this->decorated->aggregate($definition, $criteria, $context);
        }

        return $this->hydrate($criteria, $context, $result);
    }

    private function hydrateAggregation(Aggregation $aggregation, array $result, Context $context): AggregationResult
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

                return $this->hydrateAggregation($nested, $result[$nested->getName()], $context);

            case $aggregation instanceof DateHistogramAggregation:
                return $this->hydrateDateHistogram($aggregation, $result, $context);

            case $aggregation instanceof TermsAggregation:
                return $this->hydrateTermsAggregation($aggregation, $result, $context);

            default:
                throw new \RuntimeException(sprintf('Provided aggregation of class %s is not supported', get_class($aggregation)));
        }
    }

    private function hydrate(Criteria $criteria, Context $context, array $result): AggregationResultCollection
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

            $aggregations->add(
                $this->hydrateAggregation($aggregation, $aggResult, $context)
            );
        }

        return $aggregations;
    }

    private function hydrateDateHistogram(DateHistogramAggregation $aggregation, array $result, Context $context)
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

            if ($aggregation->getFormat()) {
                $value = $date->format($aggregation->getFormat());
            } else {
                $value = EntityAggregator::formatDate($aggregation->getInterval(), $date);
            }

            $buckets[] = new Bucket($value, $bucket['doc_count'], $nested);
        }

        return new DateHistogramResult($aggregation->getName(), $buckets);
    }

    private function hydrateTermsAggregation(TermsAggregation $aggregation, array $result, Context $context): ?TermsResult
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

    private function hydrateEntityAggregation(EntityAggregation $aggregation, array $result, Context $context): EntityResult
    {
        if (array_key_exists($aggregation->getName(), $result)) {
            $result = $result[$aggregation->getName()];
        }

        $ids = array_column($result['buckets'], 'key');

        $repository = $this->definitionInstanceRegistry->getRepository($aggregation->getEntity());

        $entities = $repository->search(new Criteria($ids), $context);

        return new EntityResult($aggregation->getName(), $entities->getEntities());
    }
}
