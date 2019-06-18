<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\DataAbstractionLayer;

use Elasticsearch\Client;
use ONGR\ElasticsearchDSL\Search;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Aggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\AvgAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\CountAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\EntityAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\MaxAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\MinAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\StatsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\SumAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\ValueAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\ValueCountAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AvgResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\CountResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\EntityResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\MaxResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\MinResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\StatsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\SumResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\ValueCountItem;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\ValueCountResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\ValueResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregatorResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Shopware\Elasticsearch\Framework\DefinitionRegistry;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;

class ElasticsearchEntityAggregator implements EntityAggregatorInterface
{
    /**
     * @var DefinitionRegistry
     */
    private $registry;

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
        DefinitionRegistry $registry,
        ElasticsearchHelper $helper,
        Client $client,
        EntityAggregatorInterface $decorated,
        DefinitionInstanceRegistry $definitionInstanceRegistry
    ) {
        $this->registry = $registry;
        $this->helper = $helper;
        $this->client = $client;
        $this->decorated = $decorated;
        $this->definitionInstanceRegistry = $definitionInstanceRegistry;
    }

    public function aggregate(EntityDefinition $definition, Criteria $criteria, Context $context): AggregatorResult
    {
        $criteria->resetAggregations();
        $criteria->addAggregation(new EntityAggregation('product.manufacturer.id', ProductManufacturerDefinition::class, 'EntityAggregation', 'product.categories.id'));
        $criteria->addAggregation(new AvgAggregation('product.price', 'AvgAggregation', 'product.categories.id'));
        $criteria->addAggregation(new CountAggregation('manufacturerId', 'CountAggregation', 'product.categories.id'));
        $criteria->addAggregation(new ValueCountAggregation('active', 'ValueCountAggregation', 'product.categories.id'));
        $criteria->addAggregation(new MaxAggregation('active', 'MaxAggregation', 'product.categories.id'));
        $criteria->addAggregation(new MinAggregation('active', 'MinAggregation', 'product.categories.id'));
        $criteria->addAggregation(new StatsAggregation('product.price', 'StatsAggregation', true, true, true, true, true, 'product.categories.id'));
        $criteria->addAggregation(new SumAggregation('product.price', 'SumAggregation', 'product.categories.id'));
        $criteria->addAggregation(new ValueAggregation('active', 'ValueAggregation', 'product.categories.id'));
        $criteria->addAggregation(new ValueCountAggregation('active', 'ValueCountAggregation', 'product.categories.id'));

        if (!$this->helper->allowSearch($definition, $context)) {
            return $this->decorated->aggregate($definition, $criteria, $context);
        }

        $search = new Search();
        $this->helper->addFilters($definition, $criteria, $search, $context);
        $this->helper->addQueries($definition, $criteria, $search, $context);
        $this->helper->addAggregations($definition, $criteria, $search, $context);

        $search->setSize(0);

        $result = $this->client->search([
            'index' => $this->registry->getIndex($definition, $context),
            'type' => $definition->getEntityName(),
            'body' => $search->toArray(),
        ]);

        $aggregations = $this->hydrate($criteria, $context, $result);

        return new AggregatorResult($aggregations, $context, $criteria);
    }

    private function hydrateAggregation(Aggregation $aggregation, array $result, Context $context)
    {
        switch (true) {
            case $aggregation instanceof StatsAggregation:
                return new AggregationResult(
                    $aggregation,
                    [new StatsResult(null, $result['min'], $result['max'], $result['count'], $result['avg'], $result['sum'])]
                );

            case $aggregation instanceof AvgAggregation:
                return new AggregationResult(
                    $aggregation,
                    [new AvgResult(null, $result['value'])]
                );

            case $aggregation instanceof CountAggregation:
                return new AggregationResult(
                    $aggregation,
                    [new CountResult(null, $result['value'])]
                );

            case $aggregation instanceof EntityAggregation:
                if (array_key_exists($aggregation->getName(), $result)) {
                    $result = $result[$aggregation->getName()];
                }

                $ids = array_column($result['buckets'], 'key');

                $definition = $this->definitionInstanceRegistry->get($aggregation->getDefinition());

                $repository = $this->definitionInstanceRegistry->getRepository($definition->getEntityName());

                $entities = $repository->search(new Criteria($ids), $context);

                return new AggregationResult(
                    $aggregation,
                    [new EntityResult(null, $entities->getEntities())]
                );

            case $aggregation instanceof MaxAggregation:
                return new AggregationResult(
                    $aggregation,
                    [new MaxResult(null, $result['value'])]
                );

            case $aggregation instanceof MinAggregation:
                return new AggregationResult(
                    $aggregation,
                    [new MinResult(null, $result['value'])]
                );

            case $aggregation instanceof SumAggregation:
                return new AggregationResult(
                    $aggregation,
                    [new SumResult(null, $result['value'])]
                );

            case $aggregation instanceof ValueAggregation:
                $values = array_column($result['buckets'], 'key');

                return new AggregationResult(
                    $aggregation,
                    [new ValueResult(null, $values)]
                );

            case $aggregation instanceof ValueCountAggregation:
                $values = [];
                foreach ($result['buckets'] as $bucket) {
                    $values[] = new ValueCountItem($bucket['key'], $bucket['doc_count']);
                }

                return new AggregationResult(
                    $aggregation,
                    [new ValueCountResult(null, $values)]
                );

            default:
                return null;
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

            $hydrated = $this->hydrateAggregation($aggregation, $aggResult, $context);

            if (!$hydrated) {
                // todo@dr log not supported aggregations
                continue;
            }

            $aggregations->add($hydrated);
        }

        return $aggregations;
    }
}
