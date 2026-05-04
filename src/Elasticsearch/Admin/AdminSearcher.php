<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Admin;

use OpenSearch\Client;
use OpenSearchDSL\Query\Compound\BoolQuery;
use OpenSearchDSL\Query\FullText\MatchQuery;
use OpenSearchDSL\Query\FullText\SimpleQueryStringQuery;
use OpenSearchDSL\Search;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\ElasticsearchException;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\AbstractElasticsearchSearchHydrator;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\ElasticsearchEntitySearcher;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;

/**
 * @internal
 *
 * @final
 */
#[Package('inventory')]
class AdminSearcher
{
    public function __construct(
        private readonly Client $client,
        private readonly AdminSearchRegistry $registry,
        private readonly AdminElasticsearchHelper $adminEsHelper,
        private readonly DefinitionInstanceRegistry $definitionInstanceRegistry,
        private readonly AbstractElasticsearchSearchHydrator $hydrator,
        private readonly ElasticsearchHelper $esHelper,
        private readonly string $timeout,
        private readonly int $termMaxLength,
        private readonly string $searchType
    ) {
    }

    /**
     * @param array<string> $entities
     *
     * @return array<string, array{total: int, data: EntityCollection<covariant \Shopware\Core\Framework\DataAbstractionLayer\Entity>, indexer: string, index: string}>
     */
    public function search(string $term, array $entities, Context $context, int $limit = 5): array
    {
        $indexes = [];

        $term = $this->extractTerm($term);

        foreach ($entities as $entityName) {
            if (!$context->isAllowed($entityName . ':' . AclRoleDefinition::PRIVILEGE_READ)) {
                continue;
            }

            try {
                $indexes = array_merge($indexes, $this->buildSearchPayload($entityName, $term, $limit));
            } catch (ElasticsearchException) {
                continue;
            }
        }

        if ($indexes === []) {
            return [];
        }

        $responses = $this->client->msearch(['body' => $indexes]);

        $result = $this->parseResponse($responses);

        $mapped = [];
        foreach ($result as $index => $values) {
            $entityName = $values['hits'][0]['entityName'];
            $indexer = $this->registry->getIndexer($entityName);

            $data = $indexer->globalData($values, $context);
            $data['indexer'] = $indexer->getName();
            $data['index'] = $index;

            $mapped[$indexer->getEntity()] = $data;
        }

        return $mapped;
    }

    public function searchIds(string $entityName, Criteria $criteria, Context $context): IdSearchResult
    {
        if (!Feature::isActive('ENABLE_OPENSEARCH_FOR_ADMIN_API')) {
            Feature::throwException('ENABLE_OPENSEARCH_FOR_ADMIN_API', 'Method is unavailable when the feature is active.');
        }

        if (!$context->isAllowed($entityName . ':' . AclRoleDefinition::PRIVILEGE_READ)) {
            throw ElasticsearchException::missingPrivilege([
                $entityName . ':' . AclRoleDefinition::PRIVILEGE_READ,
            ]);
        }

        $definition = $this->definitionInstanceRegistry->getByEntityName($entityName);
        $indexer = $this->registry->getIndexer($entityName);
        $query = new Search();

        if ($criteria->getTerm()) {
            $term = $this->extractTerm($criteria->getTerm());

            $query = $indexer->moduleCriteria($term, $this->buildSearch($term));
            $query->getQueries()->addParameter('minimum_should_match', 1);
        }

        $query = $this->paginate($query, $criteria->getLimit(), $criteria->getOffset());

        $this->esHelper->addQueries($definition, $criteria, $query, $context);
        $this->esHelper->addPostFilters($definition, $criteria, $query, $context);
        $this->esHelper->addFilters($definition, $criteria, $query, $context);
        $this->esHelper->addSortings($definition, $criteria, $query, $context);
        $this->esHelper->handleIds($definition, $criteria, $query, $context);
        $this->esHelper->addAggregations($definition, $criteria, $query, $context);

        $query = $query->toArray();
        $query['timeout'] = $this->timeout;

        $request = [
            'index' => $this->adminEsHelper->getIndex($indexer->getName()),
            'search_type' => $this->searchType,
            'track_total_hits' => $criteria->getTotalCountMode() === Criteria::TOTAL_COUNT_MODE_EXACT,
            'body' => $query,
        ];

        $result = $this->client->search($request);

        $ids = $this->hydrator->hydrate(
            $this->definitionInstanceRegistry->getByEntityName($entityName),
            $criteria,
            $context,
            $result
        );

        $ids->addState(ElasticsearchEntitySearcher::RESULT_STATE);

        return $ids;
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function buildSearchPayload(string $entityName, string $term, int $limit): array
    {
        $indexer = $this->registry->getIndexer($entityName);

        $alias = $this->adminEsHelper->getIndex($indexer->getName());

        $index = [];

        $index[] = [
            'index' => $alias,
            'search_type' => $this->searchType,
            'allow_no_indices' => true,
            'ignore_unavailable' => true,
        ];
        $query = $indexer->globalCriteria($term, $this->buildSearch($term));
        $this->paginate($query, $limit);

        $query = $query->toArray();

        $query['timeout'] = $this->timeout;

        $index[] = $query;

        return $index;
    }

    private function buildSearch(string $term): Search
    {
        $search = new Search();
        $splitTerms = explode(' ', $term);
        $lastPart = end($splitTerms);

        $ngramQuery = new MatchQuery('text.ngram', $term);
        $search->addQuery($ngramQuery, BoolQuery::SHOULD);

        // If the end of the search term is not a symbol, apply the prefix search query
        if (preg_match('/^[\p{L}0-9]+$/u', $lastPart)) {
            $term .= '*';
        }

        $query = new SimpleQueryStringQuery($term, [
            'fields' => ['text'],
            'lenient' => true,
        ]);
        $search->addQuery($query, BoolQuery::SHOULD);

        return $search;
    }

    private function paginate(Search $search, ?int $limit = null, ?int $offset = null): Search
    {
        if ($limit !== null) {
            $search->setSize($limit);
        }

        if ($offset !== null) {
            $search->setFrom($offset);
        }

        return $search;
    }

    private function extractTerm(string $rawTerm): string
    {
        $term = mb_substr(trim($rawTerm), 0, $this->termMaxLength);

        $term = (string) mb_eregi_replace('\s(or)\s', '|', $term);
        $term = (string) mb_eregi_replace('\s(and)\s', ' + ', $term);

        return (string) mb_eregi_replace('\s(not)\s', ' -', $term);
    }

    /**
     * @param array<mixed> $rawResponse
     *
     * @return array<string, array{total: int, hits: array<int, array{id: string, score: float, parameters: array<string, mixed>, entityName: string }>}>
     */
    private function parseResponse(array $rawResponse): array
    {
        if (!\array_key_exists('responses', $rawResponse) || !\is_array($rawResponse['responses'])) {
            return [];
        }

        $result = [];

        foreach ($rawResponse['responses'] as $response) {
            if (!isset($response['hits']['hits']) || !\is_array($response['hits']['hits'])) {
                continue;
            }

            if ($response['hits']['hits'] === []) {
                continue;
            }

            $index = $response['hits']['hits'][0]['_index'];

            $result[$index] = [
                'total' => $response['hits']['total']['value'],
                'hits' => [],
            ];

            foreach ($response['hits']['hits'] as $hit) {
                $result[$index]['hits'][] = [
                    'id' => $hit['_id'],
                    'score' => $hit['_score'],
                    'parameters' => $hit['_source']['parameters'],
                    'entityName' => $hit['_source']['entityName'],
                ];
            }
        }

        return $result;
    }
}
