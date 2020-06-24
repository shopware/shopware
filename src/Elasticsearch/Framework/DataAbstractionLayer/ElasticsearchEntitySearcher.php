<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\DataAbstractionLayer;

use Elasticsearch\Client;
use ONGR\ElasticsearchDSL\Aggregation\AbstractAggregation;
use ONGR\ElasticsearchDSL\Aggregation\Bucketing\FilterAggregation;
use ONGR\ElasticsearchDSL\Aggregation\Metric\CardinalityAggregation;
use ONGR\ElasticsearchDSL\Search;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Grouping\FieldGrouping;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\Event\ElasticsearchEntitySearcherSearchEvent;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class ElasticsearchEntitySearcher implements EntitySearcherInterface
{
    public const MAX_LIMIT = 10000;

    /**
     * @var Client
     */
    private $client;

    /**
     * @var EntitySearcherInterface
     */
    private $decorated;

    /**
     * @var ElasticsearchHelper
     */
    private $helper;

    /**
     * @var CriteriaParser
     */
    private $criteriaParser;

    /**
     * @var AbstractElasticsearchSearchHydrator
     */
    private $hydrator;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        Client $client,
        EntitySearcherInterface $searcher,
        ElasticsearchHelper $helper,
        CriteriaParser $criteriaParser,
        AbstractElasticsearchSearchHydrator $hydrator,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->client = $client;
        $this->decorated = $searcher;
        $this->helper = $helper;
        $this->criteriaParser = $criteriaParser;
        $this->hydrator = $hydrator;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function search(EntityDefinition $definition, Criteria $criteria, Context $context): IdSearchResult
    {
        if (!$this->helper->allowSearch($definition, $context)) {
            return $this->decorated->search($definition, $criteria, $context);
        }

        $search = $this->createSearch($criteria, $definition, $context);

        $this->eventDispatcher->dispatch(
            new ElasticsearchEntitySearcherSearchEvent(
                $search,
                $definition,
                $criteria,
                $context
            )
        );

        $search = $this->convertSearch($criteria, $definition, $context, $search);

        try {
            $result = $this->client->search([
                'index' => $this->helper->getIndexName($definition, $context->getLanguageId()),
                'type' => $definition->getEntityName(),
                'track_total_hits' => true,
                'body' => $search,
            ]);
        } catch (\Throwable $e) {
            $this->helper->logOrThrowException($e);

            return $this->decorated->search($definition, $criteria, $context);
        }

        return $this->hydrator->hydrate($definition, $criteria, $context, $result);
    }

    private function createSearch(Criteria $criteria, EntityDefinition $definition, Context $context): Search
    {
        $search = new Search();

        $this->helper->handleIds($definition, $criteria, $search, $context);
        $this->helper->addFilters($definition, $criteria, $search, $context);
        $this->helper->addPostFilters($definition, $criteria, $search, $context);
        $this->helper->addQueries($definition, $criteria, $search, $context);
        $this->helper->addSortings($definition, $criteria, $search, $context);
        $this->helper->addTerm($criteria, $search, $context, $definition);

        $search->setSize($criteria->getLimit());
        if ($criteria->getLimit() === null) {
            $search->setSize(self::MAX_LIMIT);
        }
        $search->setFrom($criteria->getOffset());

        return $search;
    }

    private function convertSearch(Criteria $criteria, EntityDefinition $definition, Context $context, Search $search): array
    {
        if (!$criteria->getGroupFields()) {
            return $search->toArray();
        }

        $aggregation = $this->buildTotalCountAggregation($criteria, $definition, $context);

        $search->addAggregation($aggregation);

        $array = $search->toArray();
        $array['collapse'] = $this->parseGrouping($criteria->getGroupFields(), $definition, $context);

        return $array;
    }

    /**
     * @param FieldGrouping[] $groupings
     */
    private function parseGrouping(array $groupings, EntityDefinition $definition, Context $context): array
    {
        $grouping = array_shift($groupings);

        $accessor = $this->criteriaParser->buildAccessor($definition, $grouping->getField(), $context);
        if (empty($groupings)) {
            return ['field' => $accessor];
        }

        return [
            'field' => $accessor,
            'inner_hits' => [
                'name' => 'inner',
                'collapse' => $this->parseGrouping($groupings, $definition, $context),
            ],
        ];
    }

    private function buildTotalCountAggregation(Criteria $criteria, EntityDefinition $definition, Context $context): AbstractAggregation
    {
        $groupings = $criteria->getGroupFields();

        if (count($groupings) === 1) {
            $first = array_shift($groupings);

            $accessor = $this->criteriaParser->buildAccessor($definition, $first->getField(), $context);

            $aggregation = new CardinalityAggregation('total-count');
            $aggregation->setField($accessor);

            return $this->addPostFilterAggregation($criteria, $definition, $context, $aggregation);
        }

        $fields = [];
        foreach ($groupings as $grouping) {
            $accessor = $this->criteriaParser->buildAccessor($definition, $grouping->getField(), $context);

            $fields[] = sprintf(
                "
                if (doc['%s'].size()==0) {
                    value = value + 'empty';
                } else {
                    value = value + doc['%s'].value;
                }",
                $accessor,
                $accessor
            );
        }

        $script = '
            def value = \'\';

            ' . implode(' ', $fields) . '

            return value;
        ';

        $aggregation = new CardinalityAggregation('total-count');
        $aggregation->setScript($script);

        return $this->addPostFilterAggregation($criteria, $definition, $context, $aggregation);
    }

    private function addPostFilterAggregation(Criteria $criteria, EntityDefinition $definition, Context $context, CardinalityAggregation $aggregation): AbstractAggregation
    {
        if (!$criteria->getPostFilters()) {
            return $aggregation;
        }

        $query = $this->criteriaParser->parseFilter(
            new MultiFilter(MultiFilter::CONNECTION_AND, $criteria->getPostFilters()),
            $definition,
            $definition->getEntityName(),
            $context
        );

        $filterAgg = new FilterAggregation('total-filtered-count', $query);
        $filterAgg->addAggregation($aggregation);

        return $filterAgg;
    }
}
