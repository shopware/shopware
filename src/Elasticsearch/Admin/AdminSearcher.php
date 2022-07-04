<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Admin;

use Elasticsearch\Client;
use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\FullText\MatchQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\TermQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\TermsQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\WildcardQuery;
use ONGR\ElasticsearchDSL\Search;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Tokenizer;

class AdminSearcher
{
    private Client $client;

    private Tokenizer $tokenizer;

    private AdminSearchRegistry $registry;

    /**
     * @internal
     */
    public function __construct(Client $client, Tokenizer $tokenizer, AdminSearchRegistry $registry)
    {
        $this->client = $client;
        $this->tokenizer = $tokenizer;
        $this->registry = $registry;
    }

    public function search(string $term, Context $context, int $limit = 5): array
    {
        $index = [];
        foreach ($this->registry->getIndexers() as $indexer) {
            $index[] = ['index' => $indexer->getIndex()];

            $index[] = $indexer->globalCriteria($term, $this->buildSearch($term, $limit));
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
                'hits' => []
            ];

            foreach ($response['hits']['hits'] as $hit) {
                $result[$index]['hits'][] = [
                    'id' => $hit['_id'],
                    'score' => $hit['_score'],
                    'parameters' => $hit['_source']['parameters'],
                    'entity_name' => $hit['_source']['entity_name'],
                ];
            }
        }

        $mapped = [];
        foreach ($result as $index => $values) {
            $indexer = $this->registry->getIndexer($index);

            if (!$context->isAllowed($indexer->getEntity() . ':' . AclRoleDefinition::PRIVILEGE_READ)) {
                continue;
            }

            $data = $indexer->globalData($values, $context);
            $data['indexer'] = $indexer->getName();
            $data['index'] = $indexer->getIndex();

            $mapped[] = $data;
        }

        return $mapped;
    }

    private function buildSearch(string $term, int $limit): Search
    {
        $tokens = $this->tokenizer->tokenize($term);

        $search = new Search();

        $query = new BoolQuery();
        foreach ($tokens as $token) {
            $query->add(new MatchQuery('text', $token), BoolQuery::SHOULD);
            $query->add(new WildcardQuery('text', '*' . $token . '*'), BoolQuery::SHOULD);
        }
        $query->addParameter('minimum_should_match', 1);

        $search->addQuery($query);
        $search->setSize($limit);

        return $search;
    }
}
