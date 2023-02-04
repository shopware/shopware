<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\DataAbstractionLayer;

use OpenSearch\Client;
use OpenSearchDSL\Search;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\Event\ElasticsearchEntityAggregatorSearchEvent;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('core')]
class ElasticsearchEntityAggregator implements EntityAggregatorInterface
{
    final public const RESULT_STATE = 'loaded-by-elastic';

    /**
     * @internal
     */
    public function __construct(
        private readonly ElasticsearchHelper $helper,
        private readonly Client $client,
        private readonly EntityAggregatorInterface $decorated,
        private readonly AbstractElasticsearchAggregationHydrator $hydrator,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function aggregate(EntityDefinition $definition, Criteria $criteria, Context $context): AggregationResultCollection
    {
        if (!$this->helper->allowSearch($definition, $context, $criteria)) {
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
            $this->helper->logAndThrowException($e);

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
