<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\DataAbstractionLayer;

use Elasticsearch\Client;
use ONGR\ElasticsearchDSL\Search;
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
                return new AggregationResult($aggregation, $result);

            case $aggregation instanceof AvgAggregation:
                return new AggregationResult($aggregation, $result);

            case $aggregation instanceof CountAggregation:
                return new AggregationResult($aggregation, $result);

            case $aggregation instanceof EntityAggregation:
                return new AggregationResult($aggregation, $result);

            case $aggregation instanceof MaxAggregation:
                return new AggregationResult($aggregation, $result);

            case $aggregation instanceof MinAggregation:
                return new AggregationResult($aggregation, $result);

            case $aggregation instanceof SumAggregation:
                return new AggregationResult($aggregation, $result);

            case $aggregation instanceof ValueAggregation:
                return new AggregationResult($aggregation, $result);

            case $aggregation instanceof ValueCountAggregation:
                return new AggregationResult($aggregation, $result);

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
        }

        return $aggregations;
    }
}
