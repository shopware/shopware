<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Indexing;

use OpenSearch\Client;
use Psr\EventDispatcher\EventDispatcherInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Elasticsearch\ElasticsearchException;
use Shopware\Elasticsearch\Framework\AbstractElasticsearchDefinition;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;
use Shopware\Elasticsearch\Framework\Indexing\Event\ElasticsearchIndexConfigEvent;
use Shopware\Elasticsearch\Framework\Indexing\Event\ElasticsearchIndexCreatedEvent;

#[Package('framework')]
class IndexCreator
{
    /**
     * Names of every analyzer in `elasticsearch.yaml` that already runs the
     * `word_delimiter_graph` chain. The `sw_dimension_normalize` char_filter
     * is conceptually part of that chain (it pre-collapses dimensional
     * notations before tokenization), so it gets injected into exactly these
     * analyzers when the bundle parameter is enabled.
     */
    private const TECHNICAL_TERM_ANALYZERS = [
        'sw_whitespace_technical_term_index_analyzer',
        'sw_whitespace_technical_term_search_analyzer',
        'sw_english_technical_term_index_analyzer',
        'sw_english_technical_term_search_analyzer',
        'sw_german_technical_term_index_analyzer',
        'sw_german_technical_term_search_analyzer',
    ];

    /**
     * @var array<mixed>
     */
    private readonly array $config;

    /**
     * @internal
     *
     * @param array<mixed> $config
     */
    public function __construct(
        private readonly Client $client,
        array $config,
        private readonly IndexMappingProvider $mappingProvider,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ElasticsearchHelper $helper,
        bool $dimensionNormalizeEnabled = false
    ) {
        if (isset($config['settings']['index'])) {
            if (\array_key_exists('number_of_shards', $config['settings']['index']) && $config['settings']['index']['number_of_shards'] === null) {
                unset($config['settings']['index']['number_of_shards']);
            }

            if (\array_key_exists('number_of_replicas', $config['settings']['index']) && $config['settings']['index']['number_of_replicas'] === null) {
                unset($config['settings']['index']['number_of_replicas']);
            }
        }

        if ($dimensionNormalizeEnabled) {
            $config = $this->enableDimensionNormalize($config);
        }

        $this->config = $config;
    }

    public function createIndex(AbstractElasticsearchDefinition $definition, string $index, string $alias, Context $context): void
    {
        // @codeCoverageIgnoreStart - does not execute if there's no index yet
        if ($this->indexExists($index)) {
            $this->client->indices()->delete(['index' => $index]);
        }
        // @codeCoverageIgnoreEnd

        $mapping = $this->mappingProvider->build($definition, $context);

        $body = array_merge(
            $this->config,
            ['mappings' => $mapping]
        );

        $event = new ElasticsearchIndexConfigEvent($index, $body, $definition, $context);
        $this->eventDispatcher->dispatch($event);

        try {
            $this->client->indices()->create([
                'index' => $index,
                'body' => $event->getConfig(),
            ]);
        } catch (\Throwable $exception) {
            $exception = ElasticsearchException::indexCreationFailed($index, $event->getConfig(), $exception);
            $this->helper->logAndThrowException($exception);
        }

        $this->createAliasIfNotExisting($index, $alias);

        $this->eventDispatcher->dispatch(new ElasticsearchIndexCreatedEvent($index, $definition));
    }

    public function aliasExists(string $alias): bool
    {
        return $this->client->indices()->existsAlias(['name' => $alias]);
    }

    private function indexExists(string $index): bool
    {
        return $this->client->indices()->exists(['index' => $index]);
    }

    /**
     * Prepends `sw_dimension_normalize` to the `char_filter` list of every
     * technical-term analyzer chain. The char_filter definition itself ships
     * unconditionally in `elasticsearch.yaml`; this only toggles whether the
     * analyzer chains reference it, so the same yaml is used regardless of
     * environment and the only behavioral difference is which analyzers
     * invoke the regex.
     *
     * @param array<mixed> $config
     *
     * @return array<mixed>
     */
    private function enableDimensionNormalize(array $config): array
    {
        if (!isset($config['settings']['analysis']['analyzer']) || !\is_array($config['settings']['analysis']['analyzer'])) {
            return $config;
        }

        foreach (self::TECHNICAL_TERM_ANALYZERS as $analyzer) {
            if (!isset($config['settings']['analysis']['analyzer'][$analyzer])) {
                continue;
            }

            $charFilters = $config['settings']['analysis']['analyzer'][$analyzer]['char_filter'] ?? [];
            \assert(\is_array($charFilters));

            if (\in_array('sw_dimension_normalize', $charFilters, true)) {
                continue;
            }

            // Prepend so the dimension regex runs before locale-scoped
            // char_filters (e.g. sw_decimal_normalize on German) and before
            // the universal sw_unit_glue. Order is immaterial for the
            // canonical patterns but keeping the most-specific filter first
            // matches the "analysis pipeline" mental model: pre-normalize
            // specific notational variants, then bridge generic numeric
            // boundaries.
            $config['settings']['analysis']['analyzer'][$analyzer]['char_filter'] = array_merge(
                ['sw_dimension_normalize'],
                $charFilters,
            );
        }

        return $config;
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
