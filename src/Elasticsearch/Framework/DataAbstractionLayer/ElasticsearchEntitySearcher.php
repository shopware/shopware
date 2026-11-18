<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\DataAbstractionLayer;

use OpenSearch\Client;
use OpenSearchDSL\Aggregation\AbstractAggregation;
use OpenSearchDSL\Aggregation\Bucketing\FilterAggregation;
use OpenSearchDSL\Aggregation\Metric\CardinalityAggregation;
use OpenSearchDSL\Collapse\Collapse;
use OpenSearchDSL\Search;
use OpenSearchDSL\SearchEndpoint\CollapseEndpoint;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Grouping\FieldGrouping;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\Event\ElasticsearchEntitySearcherSearchedEvent;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\Event\ElasticsearchEntitySearcherSearchEvent;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;
use Shopware\Elasticsearch\Framework\Exception\EmptyQueryException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('framework')]
class ElasticsearchEntitySearcher implements EntitySearcherInterface
{
    /**
     * @deprecated tag:v6.8.0 - Will be removed, use \Shopware\Core\Framework\Context::ELASTICSEARCH_EXPLAIN_MODE instead.
     */
    final public const EXPLAIN_MODE = Context::ELASTICSEARCH_EXPLAIN_MODE;
    final public const MAX_LIMIT = 10000;
    final public const RESULT_STATE = 'loaded-by-elastic';

    /**
     * @internal
     */
    public function __construct(
        private readonly Client $client,
        private readonly EntitySearcherInterface $decorated,
        private readonly ElasticsearchHelper $helper,
        private readonly CriteriaParser $criteriaParser,
        private readonly AbstractElasticsearchSearchHydrator $hydrator,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly string $timeout,
        private readonly string $searchType,
        private readonly ?int $precisionThreshold = null
    ) {
    }

    public function search(EntityDefinition $definition, Criteria $criteria, Context $context): IdSearchResult
    {
        if (!$this->helper->allowSearch($definition, $context, $criteria)) {
            return $this->decorated->search($definition, $criteria, $context);
        }

        if ($criteria->getLimit() === 0) {
            return new IdSearchResult(0, [], $criteria, $context);
        }

        try {
            $search = $this->createSearch($criteria, $definition, $context);

            $this->eventDispatcher->dispatch(
                new ElasticsearchEntitySearcherSearchEvent(
                    $search,
                    $definition,
                    $criteria,
                    $context
                )
            );

            $params = [
                'index' => $this->helper->getIndexName($definition),
                'search_type' => $this->searchType,
                'body' => $this->convertSearch($criteria, $definition, $context, $search),
            ];

            if ($context->hasState(Context::ELASTICSEARCH_EXPLAIN_MODE)) {
                $params['include_named_queries_score'] = true;
                $params['track_scores'] = true;
            }

            $response = $criteria->getLimit() === null && $criteria->getGroupFields() === []
                ? $this->searchAllPages($params)
                : $this->client->search($params);

            $result = $this->hydrator->hydrate($definition, $criteria, $context, $response);

            $this->eventDispatcher->dispatch(new ElasticsearchEntitySearcherSearchedEvent(
                $result,
                $search,
                $definition,
                $criteria,
                $context,
                $response,
            ));

            $result->addState(self::RESULT_STATE);

            return $result;
        } catch (\Throwable $e) {
            if ($e instanceof EmptyQueryException) {
                return new IdSearchResult(0, [], $criteria, $context);
            }

            $this->helper->logAndThrowException($e);

            return $this->decorated->search($definition, $criteria, $context);
        }
    }

    /**
     * @param array{index: string, search_type: string, body: array<string, mixed>, include_named_queries_score?: true, track_scores?: true} $params
     *
     * @return array<string, mixed>
     */
    private function searchAllPages(array $params): array
    {
        $params['allow_partial_search_results'] = false;

        /** @var list<array<string, mixed>> $sort */
        $sort = $params['body']['sort'] ?? [['_score' => ['order' => 'desc']]];
        $sort[] = ['id' => ['order' => 'asc']];
        $params['body']['sort'] = $sort;

        $response = $this->client->search($params);

        /** @var list<array{sort: list<mixed>}> $page */
        $page = $response['hits']['hits'] ?? [];
        $hits = $page;

        $params['body']['from'] = 0;
        $params['body']['track_total_hits'] = false;

        while ($page !== [] && \count($page) === ($params['body']['size'] ?? self::MAX_LIMIT)) {
            $params['body']['search_after'] = $page[\count($page) - 1]['sort'];

            /** @var list<array{sort: list<mixed>}> $page */
            $page = $this->client->search($params)['hits']['hits'] ?? [];
            array_push($hits, ...$page);
        }

        $response['hits']['hits'] = $hits;

        return $response;
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

        $search->setSize(self::MAX_LIMIT);
        $limit = $criteria->getLimit();
        if ($limit !== null) {
            $search->setSize($limit);
        }
        $search->setFrom((int) $criteria->getOffset());
        $search->setTrackTotalHits($criteria->getTotalCountMode() === Criteria::TOTAL_COUNT_MODE_EXACT);

        return $search;
    }

    /**
     * @return array<string, mixed>
     */
    private function convertSearch(Criteria $criteria, EntityDefinition $definition, Context $context, Search $search): array
    {
        if ($context->hasState(Context::ELASTICSEARCH_EXPLAIN_MODE)) {
            $search->setExplain(true);
        }

        if (!$criteria->getGroupFields()) {
            $array = $search->toArray();
            $array['timeout'] = $this->timeout;

            return $array;
        }

        if ($criteria->getTotalCountMode() === Criteria::TOTAL_COUNT_MODE_EXACT) {
            $aggregation = $this->buildTotalCountAggregation($criteria, $definition, $context);

            $search->addAggregation($aggregation);
        }

        $search->getEndpoint(CollapseEndpoint::NAME)->add(
            $this->parseGrouping($criteria->getGroupFields(), $definition, $context)
        );

        $array = $search->toArray();
        $array['timeout'] = $this->timeout;

        return $array;
    }

    /**
     * @param FieldGrouping[] $groupings
     */
    private function parseGrouping(array $groupings, EntityDefinition $definition, Context $context): Collapse
    {
        /** @var FieldGrouping $grouping */
        $grouping = array_shift($groupings);

        $accessor = $this->criteriaParser->buildAccessor($definition, $grouping->getField(), $context);
        $collapse = new Collapse($accessor);

        if ($groupings === []) {
            return $collapse;
        }

        $collapse->addParameter('inner_hits', [
            [
                'name' => 'inner',
                'collapse' => $this->parseGrouping($groupings, $definition, $context)->toArray(),
            ],
        ]);

        return $collapse;
    }

    private function buildTotalCountAggregation(Criteria $criteria, EntityDefinition $definition, Context $context): AbstractAggregation
    {
        $groupings = $criteria->getGroupFields();

        if (\count($groupings) === 1) {
            $first = array_shift($groupings);

            $accessor = $this->criteriaParser->buildAccessor($definition, $first->getField(), $context);

            $aggregation = new CardinalityAggregation('total-count');
            $aggregation->setField($accessor);
            if ($this->precisionThreshold !== null) {
                $aggregation->addParameter('precision_threshold', $this->precisionThreshold);
            }

            return $this->addPostFilterAggregation($criteria, $definition, $context, $aggregation);
        }

        $fields = [];
        foreach ($groupings as $grouping) {
            $fields[] = $this->criteriaParser->buildAccessor($definition, $grouping->getField(), $context);
        }

        $script = '
            def value = \'\';

            for (def field : params.fields) {
                if (doc[field].size()==0) {
                    value = value + \'empty\';
                } else {
                    value = value + doc[field].value;
                }
            }

            return value;
        ';

        $aggregation = new CardinalityAggregation('total-count');
        $aggregation->addParameter('script', [
            'source' => $script,
            'params' => ['fields' => $fields],
        ]);
        if ($this->precisionThreshold !== null) {
            $aggregation->addParameter('precision_threshold', $this->precisionThreshold);
        }

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
