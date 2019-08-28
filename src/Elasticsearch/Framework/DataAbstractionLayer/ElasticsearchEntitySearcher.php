<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\DataAbstractionLayer;

use Elasticsearch\Client;
use ONGR\ElasticsearchDSL\Aggregation\Metric\CardinalityAggregation;
use ONGR\ElasticsearchDSL\Search;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Grouping\FieldGrouping;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;

class ElasticsearchEntitySearcher implements EntitySearcherInterface
{
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

    public function __construct(
        Client $client,
        EntitySearcherInterface $searcher,
        ElasticsearchHelper $helper
    ) {
        $this->client = $client;
        $this->decorated = $searcher;
        $this->helper = $helper;
    }

    public function search(EntityDefinition $definition, Criteria $criteria, Context $context): IdSearchResult
    {
        if (!$this->helper->allowSearch($definition, $context)) {
            return $this->decorated->search($definition, $criteria, $context);
        }

        $search = $this->createSearch($criteria, $definition, $context);

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

        return $this->hydrate($criteria, $context, $result);
    }

    protected function createSearch(Criteria $criteria, EntityDefinition $definition, Context $context): Search
    {
        $search = new Search();

        $this->helper->handleIds($definition, $criteria, $search, $context);
        $this->helper->addFilters($definition, $criteria, $search, $context);
        $this->helper->addPostFilters($definition, $criteria, $search, $context);
        $this->helper->addQueries($definition, $criteria, $search, $context);
        $this->helper->addSortings($definition, $criteria, $search, $context);
        $this->helper->addTerm($criteria, $search, $context);

        $search->setSize($criteria->getLimit());
        $search->setFrom($criteria->getOffset());

        return $search;
    }

    private function hydrate(Criteria $criteria, Context $context, array $result): IdSearchResult
    {
        if (!isset($result['hits'])) {
            return new IdSearchResult(0, [], $criteria, $context);
        }

        $hits = $result['hits']['hits'];

        $data = [];
        foreach ($hits as $hit) {
            $id = $hit['_id'];

            $data[$id] = [
                'primaryKey' => $id,
                'data' => [
                    'id' => $id,
                    '_score' => $hit['_score'],
                ],
            ];
        }

        $total = (int) $result['hits']['total']['value'];
        if ($criteria->getGroupFields()) {
            $total = (int) $result['aggregations']['total-count']['value'];
        }

        return new IdSearchResult($total, $data, $criteria, $context);
    }

    private function convertSearch(Criteria $criteria, EntityDefinition $definition, Context $context, Search $search)
    {
        if (!$criteria->getGroupFields()) {
            return $search->toArray();
        }

        $fields = array_map(function (FieldGrouping $grouping) {
            return "doc['" . $grouping->getField() . "'].value";
        }, $criteria->getGroupFields());

        $fields = implode(" + ' ' + ", $fields);

        $aggregation = new CardinalityAggregation('total-count');
        $aggregation->setScript($fields);

        $search->addAggregation($aggregation);

        $array = $search->toArray();
        $array['collapse'] = $this->parseGrouping($criteria->getGroupFields());

        return $array;
    }

    /**
     * @param FieldGrouping[] $groupings
     */
    private function parseGrouping(array $groupings): array
    {
        $grouping = array_shift($groupings);

        if (empty($groupings)) {
            return ['field' => $grouping->getField()];
        }

        return [
            'field' => $grouping->getField(),
            'inner_hits' => [
                'name' => 'inner',
                'collapse' => $this->parseGrouping($groupings),
            ],
        ];
    }
}
