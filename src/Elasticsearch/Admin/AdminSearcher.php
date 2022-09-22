<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Admin;

use Elasticsearch\Client;
use ONGR\ElasticsearchDSL\Query\FullText\QueryStringQuery;
use ONGR\ElasticsearchDSL\Search;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @internal
 */
final class AdminSearcher
{
    private Client $client;

    private AdminSearchRegistry $registry;

    /**
     * @internal
     */
    public function __construct(Client $client, AdminSearchRegistry $registry)
    {
        $this->client = $client;
        $this->registry = $registry;
    }

    /**
     * @param array<string> $entities
     *
     * @return array<string, array{total: int, data:EntityCollection<Entity>, indexer: string, index: string}>
     */
    public function search(string $term, array $entities, Context $context, int $limit = 5): array
    {
        $index = [];
        foreach ($this->registry->getIndexers() as $indexer) {
            if (!\in_array($indexer->getEntity(), $entities, true)) {
                continue;
            }

            $index[] = ['index' => $indexer->getIndex()];

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
            $alias = explode('_', (string) $index);
            $alias = array_shift($alias);
            $indexer = $this->registry->getIndexer((string) $alias);

            if (!$context->isAllowed($indexer->getEntity() . ':' . AclRoleDefinition::PRIVILEGE_READ)) {
                continue;
            }

            $data = $indexer->globalData($values, $context);
            $data['indexer'] = $indexer->getName();
            $data['index'] = $indexer->getIndex();

            $mapped[$indexer->getEntity()] = $data;
        }

        return $mapped;
    }

    private function buildSearch(string $term, int $limit): Search
    {
        $term = str_replace(' or ', ' OR ', $term);
        $term = str_replace(' and ', ' AND ', $term);
        $search = new Search();
        $query = new QueryStringQuery($term);

        $search->addQuery($query);
        $search->setSize($limit);

        return $search;
    }
}
