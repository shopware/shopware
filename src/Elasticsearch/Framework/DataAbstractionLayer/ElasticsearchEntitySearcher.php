<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\DataAbstractionLayer;

use OpenSearch\Client;
use OpenSearchDSL\Aggregation\AbstractAggregation;
use OpenSearchDSL\Aggregation\Bucketing\FilterAggregation;
use OpenSearchDSL\Aggregation\Metric\CardinalityAggregation;
use OpenSearchDSL\Search;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Grouping\FieldGrouping;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\Event\ElasticsearchEntitySearcherSearchEvent;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('core')]
class ElasticsearchEntitySearcher implements EntitySearcherInterface
{
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
        private readonly EventDispatcherInterface $eventDispatcher
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
                'track_total_hits' => true,
                'body' => $search,
            ]);
        } catch (\Throwable $e) {
            $this->helper->logAndThrowException($e);

            return $this->decorated->search($definition, $criteria, $context);
        }

        $result = $this->hydrator->hydrate($definition, $criteria, $context, $result);

        $result->addState(self::RESULT_STATE);

        return $result;
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

        return $search;
    }

    /**
     * @return array<string, mixed>
     */
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
     *
     * @return array{field: string, inner_hits?: array{name: string}}
     */
    private function parseGrouping(array $groupings, EntityDefinition $definition, Context $context): array
    {
        /** @var FieldGrouping $grouping */
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

        if (\count($groupings) === 1) {
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
                '
                if (doc[\'%s\'].size()==0) {
                    value = value + \'empty\';
                } else {
                    value = value + doc[\'%s\'].value;
                }',
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
