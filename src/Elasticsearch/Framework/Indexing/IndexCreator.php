<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Indexing;

use Elasticsearch\Client;
use Shopware\Core\Framework\Context;
use Shopware\Elasticsearch\Framework\AbstractElasticsearchDefinition;

class IndexCreator
{
    private Client $client;

    private array $config;

    private array $mapping;

    public function __construct(Client $client, array $config, array $mapping = [])
    {
        $this->client = $client;
        $this->config = $config;
        $this->mapping = $mapping;
    }

    public function createIndex(AbstractElasticsearchDefinition $definition, string $index, string $alias, Context $context): void
    {
        if ($this->indexExists($index)) {
            $this->client->indices()->delete(['index' => $index]);
        }

        $mapping = $definition->getMapping($context);

        $mapping = $this->addFullText($mapping);

        $mapping = array_merge_recursive($mapping, $this->mapping);

        $this->client->indices()->create([
            'index' => $index,
            'body' => array_merge(
                $this->config,
                ['mappings' => $mapping]
            ), ]);

        $this->createAliasIfNotExisting($index, $alias);
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

    private function createAliasIfNotExisting(string $index, string $alias): void
    {
        $exist = $this->client->indices()->existsAlias(['name' => $alias]);

        if ($exist) {
            return;
        }

        $this->client->indices()->refresh([
            'index' => $index,
        ]);

        $this->client->indices()->putAlias(['index' => $index, 'name' => $alias]);
    }
}
