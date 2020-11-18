<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Indexing;

use Elasticsearch\Client;
use Shopware\Core\Framework\Context;
use Shopware\Elasticsearch\Framework\AbstractElasticsearchDefinition;

class IndexCreator
{
    /**
     * @var Client
     */
    private $client;

    /**
     * @var array
     */
    private $config;

    /**
     * @var array
     */
    private $mapping;

    public function __construct(Client $client, array $config, array $mapping = [])
    {
        $this->client = $client;
        $this->config = $config;
        $this->mapping = $mapping;
    }

    public function createIndex(AbstractElasticsearchDefinition $definition, string $index, Context $context): void
    {
        if ($this->indexExists($index)) {
            $this->client->indices()->delete(['index' => $index]);
        }

        $this->client->indices()->create([
            'index' => $index,
            'body' => $this->config,
        ]);

        $mapping = $definition->getMapping($context);

        $mapping = $this->addFullText($mapping);

        $mapping = array_merge_recursive($mapping, $this->mapping);

        $this->client->indices()->putMapping([
            'index' => $index,
            'type' => $definition->getEntityDefinition()->getEntityName(),
            'body' => $mapping,
            'include_type_name' => true,
        ]);

        $this->client->indices()->putSettings([
            'index' => $index,
            'body' => [
                'number_of_replicas' => 0,
                'refresh_interval' => -1,
            ],
        ]);
    }

    private function indexExists(string $index): bool
    {
        return $this->client->indices()->exists(['index' => $index]);
    }

    private function addFullText(array $mapping): array
    {
        $mapping['properties']['fullText'] = [
            'type' => 'text',
            'fields' => [
                'ngram' => ['type' => 'text', 'analyzer' => 'sw_ngram_analyzer'],
            ],
        ];

        $mapping['properties']['fullTextBoosted'] = ['type' => 'text'];

        if (!\array_key_exists('_source', $mapping)) {
            return $mapping;
        }

        if (!\array_key_exists('includes', $mapping['_source'])) {
            return $mapping;
        }

        $mapping['_source']['includes'][] = 'fullText';
        $mapping['_source']['includes'][] = 'fullTextBoosted';

        return $mapping;
    }
}
