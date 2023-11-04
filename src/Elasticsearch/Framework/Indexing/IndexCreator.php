<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Indexing;

use OpenSearch\Client;
use Psr\EventDispatcher\EventDispatcherInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\Framework\AbstractElasticsearchDefinition;
use Shopware\Elasticsearch\Framework\Indexing\Event\ElasticsearchIndexConfigEvent;
use Shopware\Elasticsearch\Framework\Indexing\Event\ElasticsearchIndexCreatedEvent;

#[Package('core')]
class IndexCreator
{
    /**
     * @var array<mixed>
     */
    private readonly array $config;

    /**
     * @internal
     *
     * @param array<mixed> $config
     * @param array<mixed> $mapping
     */
    public function __construct(
        private readonly Client $client,
        array $config,
        private readonly array $mapping,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
        if (isset($config['settings']['index'])) {
            if (\array_key_exists('number_of_shards', $config['settings']['index']) && $config['settings']['index']['number_of_shards'] === null) {
                unset($config['settings']['index']['number_of_shards']);
            }

            if (\array_key_exists('number_of_replicas', $config['settings']['index']) && $config['settings']['index']['number_of_replicas'] === null) {
                unset($config['settings']['index']['number_of_replicas']);
            }
        }

        $this->config = $config;
    }

    public function createIndex(AbstractElasticsearchDefinition $definition, string $index, string $alias, Context $context): void
    {
        // NEXT-21735 - does not execute if there's no index yet
        // @codeCoverageIgnoreStart
        if ($this->client->indices()->exists(['index' => $index])) {
            $this->client->indices()->delete(['index' => $index]);
        }
        // @codeCoverageIgnoreEnd

        $mapping = $definition->getMapping($context);

        $mapping = $this->addFullText($mapping);

        $mapping = array_merge_recursive($mapping, $this->mapping);

        $body = array_merge(
            $this->config,
            ['mappings' => $mapping]
        );

        $event = new ElasticsearchIndexConfigEvent($index, $body, $definition, $context);
        $this->eventDispatcher->dispatch($event);

        $this->client->indices()->create([
            'index' => $index,
            'body' => $event->getConfig(),
        ]);

        $this->createAliasIfNotExisting($index, $alias);

        $this->eventDispatcher->dispatch(new ElasticsearchIndexCreatedEvent($index, $definition));
    }

    public function aliasExists(string $alias): bool
    {
        return $this->client->indices()->existsAlias(['name' => $alias]);
    }

    /**
     * @param array<mixed> $mapping
     *
     * @return array<mixed>
     */
    private function addFullText(array $mapping): array
    {
        $mapping['properties']['fullText'] = [
            'type' => 'text',
            'fields' => [
                'ngram' => ['type' => 'text', 'analyzer' => 'sw_ngram_analyzer'],
            ],
        ];

        $mapping['properties']['fullTextBoosted'] = ['type' => 'text'];

        if (!\array_key_exists('_source', $mapping) || !\array_key_exists('includes', $mapping['_source'])) {
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
