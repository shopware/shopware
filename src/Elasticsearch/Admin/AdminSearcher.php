<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Admin;

use OpenSearch\Client;
use OpenSearchDSL\Query\Compound\BoolQuery;
use OpenSearchDSL\Query\FullText\QueryStringQuery;
use OpenSearchDSL\Search;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @package system-settings
 *
 * @internal
 *
 * @final
 */
class AdminSearcher
{
    private Client $client;

    private AdminSearchRegistry $registry;

    private AdminElasticsearchHelper $adminEsHelper;

    public function __construct(Client $client, AdminSearchRegistry $registry, AdminElasticsearchHelper $adminEsHelper)
    {
        $this->client = $client;
        $this->registry = $registry;
        $this->adminEsHelper = $adminEsHelper;
    }

    /**
     * @param array<string> $entities
     *
     * @return array<string, array{total: int, data:EntityCollection<Entity>, indexer: string, index: string}>
     */
    public function search(string $term, array $entities, Context $context, int $limit = 5): array
    {
        $index = [];
        foreach ($entities as $entityName) {
            if (!$context->isAllowed($entityName . ':' . AclRoleDefinition::PRIVILEGE_READ)) {
                continue;
            }

            $indexer = $this->registry->getIndexer($entityName);
            $alias = $this->adminEsHelper->getIndex($indexer->getName());
            $index[] = ['index' => $alias];

            $index[] = $indexer->globalCriteria($term, $this->buildSearch($term, $limit))->toArray();
        }

        $responses = $this->client->msearch(['body' => $index]);

        $result = [];
        foreach ($responses['responses'] as $response) {
            if (empty($response['hits']['hits'])) {
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

        $mapped = [];
        foreach ($result as $index => $values) {
            $entityName = $values['hits'][0]['entityName'];
            $indexer = $this->registry->getIndexer($entityName);

            $data = $indexer->globalData($values, $context);
            $data['indexer'] = $indexer->getName();
            $data['index'] = (string) $index;

            $mapped[$indexer->getEntity()] = $data;
        }

        return $mapped;
    }

    private function buildSearch(string $term, int $limit): Search
    {
        $term = mb_ereg_replace(' or ', ' OR ', $term);
        $term = mb_ereg_replace(' and ', ' AND ', (string) $term);
        $term = (string) $term;

        $search = new Search();
        $queries = [
            new QueryStringQuery($term, ['fields' => ['text'], 'boost' => 5]), // support simple query string syntax
        ];

        $splitTerms = explode(' ', $term);
        $lastPart = array_pop($splitTerms);

        // If the end of the search term is a word, apply the prefix search query
        if (preg_match('/^[a-zA-Z0-9]+$/', $lastPart)) {
            $queries[] = new QueryStringQuery($term . '*', ['fields' => ['text']]);
        }

        foreach ($queries as $query) {
            $search->addQuery($query, BoolQuery::SHOULD);
        }

        $search->setSize($limit);

        return $search;
    }
}
