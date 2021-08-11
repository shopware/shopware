<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\DataAbstractionLayer;

use Elasticsearch\Client;
use ONGR\ElasticsearchDSL\Search;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\Event\ElasticsearchEntityAggregatorSearchEvent;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class ElasticsearchEntityAggregator implements EntityAggregatorInterface
{
    public const RESULT_STATE = 'loaded-by-elastic';

    private ElasticsearchHelper $helper;

    private Client $client;

    private EntityAggregatorInterface $decorated;

    private AbstractElasticsearchAggregationHydrator $hydrator;

    private EventDispatcherInterface $eventDispatcher;

    public function __construct(
        ElasticsearchHelper $helper,
        Client $client,
        EntityAggregatorInterface $decorated,
        AbstractElasticsearchAggregationHydrator $hydrator,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->helper = $helper;
        $this->client = $client;
        $this->decorated = $decorated;
        $this->hydrator = $hydrator;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function aggregate(EntityDefinition $definition, Criteria $criteria, Context $context): AggregationResultCollection
    {
        if (!$this->helper->allowSearch($definition, $context)) {
            return $this->decorated->aggregate($definition, $criteria, $context);
        }

        $search = $this->createSearch($definition, $criteria, $context);

        $this->eventDispatcher->dispatch(
            new ElasticsearchEntityAggregatorSearchEvent($search, $definition, $criteria, $context)
        );

        try {
            $result = $this->client->search([
                'index' => $this->helper->getIndexName($definition, $context->getLanguageId()),
                'body' => $search->toArray(),
            ]);
        } catch (\Throwable $e) {
            $this->helper->logOrThrowException($e);

            return $this->decorated->aggregate($definition, $criteria, $context);
        }

        $result = $this->hydrator->hydrate($definition, $criteria, $context, $result);
        $result->addState(self::RESULT_STATE);

        return $result;
    }

    private function createSearch(EntityDefinition $definition, Criteria $criteria, Context $context): Search
    {
        $search = new Search();
        $this->helper->addFilters($definition, $criteria, $search, $context);
        $this->helper->addQueries($definition, $criteria, $search, $context);
        $this->helper->addAggregations($definition, $criteria, $search, $context);
        $this->helper->addTerm($criteria, $search, $context, $definition);
        $this->helper->handleIds($definition, $criteria, $search, $context);
        $search->setSize(0);

        return $search;
    }
}
