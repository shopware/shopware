<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Framework\Indexing;

use OpenSearch\Client;
use OpenSearch\Namespaces\IndicesNamespace;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Elasticsearch\ElasticsearchException;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;
use Shopware\Elasticsearch\Framework\Indexing\Event\ElasticsearchIndexConfigEvent;
use Shopware\Elasticsearch\Framework\Indexing\Event\ElasticsearchIndexCreatedEvent;
use Shopware\Elasticsearch\Framework\Indexing\IndexCreator;
use Shopware\Elasticsearch\Framework\Indexing\IndexMappingProvider;
use Shopware\Elasticsearch\Product\ElasticsearchProductDefinition;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[CoversClass(IndexCreator::class)]
class IndexCreatorTest extends TestCase
{
    /**
     * @param array<mixed> $constructorConfig
     * @param array<mixed> $expectedConfig
     */
    #[DataProvider('providerCreateIndices')]
    public function testIndexCreation(array $constructorConfig, array $expectedConfig): void
    {
        $client = $this->createMock(Client::class);
        $indices = $this->createMock(IndicesNamespace::class);
        $indices
            ->expects($this->once())
            ->method('create')
            ->with([
                'index' => 'foo',
                'body' => [
                    'settings' => $expectedConfig,
                    'mappings' => [
                    ],
                ],
            ]);

        // Alias does not exist, swap directly
        $indices->expects($this->once())->method('existsAlias')->with(['name' => 'bla'])->willReturn(false);
        $indices->expects($this->once())->method('refresh')->with(['index' => 'foo']);
        $indices->expects($this->once())->method('putAlias')->with(['index' => 'foo', 'name' => 'bla']);

        $client
            ->method('indices')
            ->willReturn($indices);

        $helper = $this->createMock(ElasticsearchHelper::class);
        $helper->expects($this->never())->method('logAndThrowException');

        $index = new IndexCreator(
            $client,
            [
                'settings' => $constructorConfig,
            ],
            $this->createMock(IndexMappingProvider::class),
            new EventDispatcher(),
            $helper
        );

        $definition = $this->createMock(ElasticsearchProductDefinition::class);
        $index->createIndex($definition, 'foo', 'bla', Context::createDefaultContext());
    }

    public function testIndexCreationFiresEvents(): void
    {
        $client = $this->createMock(Client::class);
        $indices = $this->createMock(IndicesNamespace::class);
        $indices
            ->expects($this->once())
            ->method('create')
            ->willReturnCallback(static function (array $config): void {
                static::assertArrayHasKey('body', $config);
                static::assertArrayHasKey('event', $config['body']);
                static::assertTrue($config['body']['event']);
            });

        // Alias does not exist, swap directly
        $indices->expects($this->once())->method('existsAlias')->with(['name' => 'bla'])->willReturn(false);
        $indices->expects($this->once())->method('refresh')->with(['index' => 'foo']);
        $indices->expects($this->once())->method('putAlias')->with(['index' => 'foo', 'name' => 'bla']);

        $client
            ->method('indices')
            ->willReturn($indices);

        $eventDispatcher = new EventDispatcher();
        $helper = $this->createMock(ElasticsearchHelper::class);
        $helper->expects($this->never())->method('logAndThrowException');

        $index = new IndexCreator(
            $client,
            [
                'settings' => [],
            ],
            $this->createMock(IndexMappingProvider::class),
            $eventDispatcher,
            $helper
        );

        $calledCreateEvent = false;
        $eventDispatcher->addListener(ElasticsearchIndexCreatedEvent::class, static function (ElasticsearchIndexCreatedEvent $event) use (&$calledCreateEvent): void {
            $calledCreateEvent = true;
            static::assertSame('foo', $event->getIndexName());
            static::assertInstanceOf(ElasticsearchProductDefinition::class, $event->getDefinition());
        });

        $calledConfigEvent = false;
        $eventDispatcher->addListener(ElasticsearchIndexConfigEvent::class, static function (ElasticsearchIndexConfigEvent $event) use (&$calledConfigEvent): void {
            $calledConfigEvent = true;

            $event->setConfig($event->getConfig() + ['event' => true]);
        });

        $definition = $this->createMock(ElasticsearchProductDefinition::class);
        $index->createIndex($definition, 'foo', 'bla', Context::createDefaultContext());

        static::assertTrue($calledCreateEvent, 'Event ElasticsearchIndexCreatedEvent was not dispatched');
        static::assertTrue($calledConfigEvent, 'Event ElasticsearchIndexConfigEvent was not dispatched');
    }

    public function testCreateIndexWithSourceField(): void
    {
        $client = $this->createMock(Client::class);
        $indices = $this->createMock(IndicesNamespace::class);
        $indices
            ->expects($this->once())
            ->method('create')
            ->with([
                'index' => 'foo',
                'body' => [
                    'mappings' => [
                        'properties' => [
                        ],
                        '_source' => ['includes' => ['foo', 'fullText', 'fullTextBoosted']],
                    ],
                ],
            ]);

        $client
            ->method('indices')
            ->willReturn($indices);

        $mappingProvider = $this->createMock(IndexMappingProvider::class);
        $mappingProvider
            ->method('build')
            ->willReturn([
                'properties' => [
                ],
                '_source' => [
                    'includes' => ['foo', 'fullText', 'fullTextBoosted'],
                ],
            ]);

        $helper = $this->createMock(ElasticsearchHelper::class);
        $helper->expects($this->never())->method('logAndThrowException');

        $index = new IndexCreator(
            $client,
            [],
            $mappingProvider,
            new EventDispatcher(),
            $helper
        );

        $definition = $this->createMock(ElasticsearchProductDefinition::class);

        $index->createIndex($definition, 'foo', 'bla', Context::createDefaultContext());
    }

    public function testCreateIndexWithAliasExists(): void
    {
        $client = $this->createMock(Client::class);
        $indices = $this->createMock(IndicesNamespace::class);
        $indices
            ->expects($this->once())
            ->method('create')
            ->with([
                'index' => 'foo',
                'body' => [
                    'mappings' => [
                    ],
                ],
            ]);

        // Alias does not exist, swap directly
        $indices->expects($this->once())->method('existsAlias')->with(['name' => 'bla'])->willReturn(true);
        $indices->expects($this->never())->method('refresh');
        $indices->expects($this->never())->method('putAlias');

        $client
            ->method('indices')
            ->willReturn($indices);

        $helper = $this->createMock(ElasticsearchHelper::class);
        $helper->expects($this->never())->method('logAndThrowException');

        $index = new IndexCreator(
            $client,
            [],
            $this->createMock(IndexMappingProvider::class),
            new EventDispatcher(),
            $helper
        );

        $definition = $this->createMock(ElasticsearchProductDefinition::class);

        $index->createIndex($definition, 'foo', 'bla', Context::createDefaultContext());
    }

    public function testAliasExists(): void
    {
        $client = $this->createMock(Client::class);
        $indices = $this->createMock(IndicesNamespace::class);
        $indices->method('existsAlias')->with(['name' => 'foo'])->willReturn(true);

        $client
            ->method('indices')
            ->willReturn($indices);

        $helper = $this->createMock(ElasticsearchHelper::class);
        $helper->expects($this->never())->method('logAndThrowException');

        $index = new IndexCreator(
            $client,
            [],
            $this->createMock(IndexMappingProvider::class),
            new EventDispatcher(),
            $helper
        );

        static::assertTrue($index->aliasExists('foo'));
    }

    public function testIndexCreationLogsWhenClientThrows(): void
    {
        $client = $this->createMock(Client::class);
        $indices = $this->createMock(IndicesNamespace::class);
        $client->method('indices')->willReturn($indices);

        $indices->expects($this->once())
            ->method('create')
            ->willThrowException(new \RuntimeException('boom'));
        $indices->expects($this->never())->method('refresh');
        $indices->expects($this->never())->method('putAlias');
        $indices->expects($this->never())->method('existsAlias');

        $helper = $this->createMock(ElasticsearchHelper::class);

        $helper->expects($this->once())
            ->method('logAndThrowException')
            ->with(static::callback(static function (ElasticsearchException $exception): bool {
                static::assertSame(ElasticsearchException::INDEX_CREATION_ERROR, $exception->getErrorCode());
                static::assertSame('foo', $exception->getParameters()['index'] ?? null);

                return true;
            }))
            ->willThrowException(new \RuntimeException('handled'));

        $index = new IndexCreator(
            $client,
            [],
            $this->createMock(IndexMappingProvider::class),
            new EventDispatcher(),
            $helper
        );

        $definition = $this->createMock(ElasticsearchProductDefinition::class);

        $this->expectExceptionObject(new \RuntimeException('handled'));

        $index->createIndex($definition, 'foo', 'alias', Context::createDefaultContext());
    }

    public function testDimensionNormalizeDisabledDoesNotInjectCharFilter(): void
    {
        $analysis = self::technicalTermAnalysisFixture();

        $client = $this->createMock(Client::class);
        $indices = $this->createMock(IndicesNamespace::class);
        $indices
            ->expects($this->once())
            ->method('create')
            ->willReturnCallback(static function (array $payload) use ($analysis): void {
                static::assertSame(
                    $analysis,
                    $payload['body']['settings']['analysis'],
                    'Analyzer chains must remain untouched when dimension_normalize is disabled.',
                );
            });
        $indices->method('existsAlias')->willReturn(true);
        $client->method('indices')->willReturn($indices);

        $helper = $this->createMock(ElasticsearchHelper::class);
        $index = new IndexCreator(
            $client,
            ['settings' => ['analysis' => $analysis]],
            $this->createMock(IndexMappingProvider::class),
            new EventDispatcher(),
            $helper,
            false
        );

        $index->createIndex($this->createMock(ElasticsearchProductDefinition::class), 'foo', 'alias', Context::createDefaultContext());
    }

    public function testDimensionNormalizeEnabledPrependsCharFilterToTechnicalTermAnalyzers(): void
    {
        $client = $this->createMock(Client::class);
        $indices = $this->createMock(IndicesNamespace::class);
        $indices
            ->expects($this->once())
            ->method('create')
            ->willReturnCallback(static function (array $payload): void {
                $analyzers = $payload['body']['settings']['analysis']['analyzer'];

                // Non-German chains had `['sw_unit_glue']`; dimension is prepended.
                static::assertSame(['sw_dimension_normalize', 'sw_unit_glue'], $analyzers['sw_whitespace_technical_term_index_analyzer']['char_filter']);
                static::assertSame(['sw_dimension_normalize', 'sw_unit_glue'], $analyzers['sw_whitespace_technical_term_search_analyzer']['char_filter']);
                static::assertSame(['sw_dimension_normalize', 'sw_unit_glue'], $analyzers['sw_english_technical_term_index_analyzer']['char_filter']);
                static::assertSame(['sw_dimension_normalize', 'sw_unit_glue'], $analyzers['sw_english_technical_term_search_analyzer']['char_filter']);

                // German chains had `['sw_decimal_normalize', 'sw_unit_glue']`; dimension is prepended, decimal + unit_glue preserved.
                static::assertSame(['sw_dimension_normalize', 'sw_decimal_normalize', 'sw_unit_glue'], $analyzers['sw_german_technical_term_index_analyzer']['char_filter']);
                static::assertSame(['sw_dimension_normalize', 'sw_decimal_normalize', 'sw_unit_glue'], $analyzers['sw_german_technical_term_search_analyzer']['char_filter']);

                // Non-technical analyzers are untouched.
                static::assertArrayNotHasKey('char_filter', $analyzers['sw_whitespace_analyzer']);
                static::assertArrayNotHasKey('char_filter', $analyzers['sw_ngram_analyzer']);
            });
        $indices->method('existsAlias')->willReturn(true);
        $client->method('indices')->willReturn($indices);

        $helper = $this->createMock(ElasticsearchHelper::class);
        $index = new IndexCreator(
            $client,
            ['settings' => ['analysis' => self::technicalTermAnalysisFixture()]],
            $this->createMock(IndexMappingProvider::class),
            new EventDispatcher(),
            $helper,
            true
        );

        $index->createIndex($this->createMock(ElasticsearchProductDefinition::class), 'foo', 'alias', Context::createDefaultContext());
    }

    public function testDimensionNormalizeEnabledIsIdempotent(): void
    {
        $client = $this->createMock(Client::class);
        $indices = $this->createMock(IndicesNamespace::class);
        $indices
            ->expects($this->once())
            ->method('create')
            ->willReturnCallback(static function (array $payload): void {
                static::assertSame(
                    ['sw_dimension_normalize', 'sw_unit_glue'],
                    $payload['body']['settings']['analysis']['analyzer']['sw_english_technical_term_index_analyzer']['char_filter'],
                    'sw_dimension_normalize must not be appended twice when already present.',
                );
            });
        $indices->method('existsAlias')->willReturn(true);
        $client->method('indices')->willReturn($indices);

        $analysis = self::technicalTermAnalysisFixture();
        $analysis['analyzer']['sw_english_technical_term_index_analyzer']['char_filter'] = ['sw_dimension_normalize', 'sw_unit_glue'];

        $helper = $this->createMock(ElasticsearchHelper::class);
        $index = new IndexCreator(
            $client,
            ['settings' => ['analysis' => $analysis]],
            $this->createMock(IndexMappingProvider::class),
            new EventDispatcher(),
            $helper,
            true
        );

        $index->createIndex($this->createMock(ElasticsearchProductDefinition::class), 'foo', 'alias', Context::createDefaultContext());
    }

    public function testDimensionNormalizeWithoutAnalysisSectionIsNoOp(): void
    {
        $client = $this->createMock(Client::class);
        $indices = $this->createMock(IndicesNamespace::class);
        $indices
            ->expects($this->once())
            ->method('create')
            ->willReturnCallback(static function (array $payload): void {
                static::assertSame(['index' => ['number_of_shards' => 1]], $payload['body']['settings']);
            });
        $indices->method('existsAlias')->willReturn(true);
        $client->method('indices')->willReturn($indices);

        $helper = $this->createMock(ElasticsearchHelper::class);
        $index = new IndexCreator(
            $client,
            ['settings' => ['index' => ['number_of_shards' => 1]]],
            $this->createMock(IndexMappingProvider::class),
            new EventDispatcher(),
            $helper,
            true
        );

        $index->createIndex($this->createMock(ElasticsearchProductDefinition::class), 'foo', 'alias', Context::createDefaultContext());
    }

    /**
     * @return iterable<array<mixed>>
     */
    public static function providerCreateIndices(): iterable
    {
        yield 'with given number of shards' => [
            [
                'index' => [
                    'number_of_shards' => 1,
                    'number_of_replicas' => 5,
                ],
            ],
            [
                'index' => [
                    'number_of_shards' => 1,
                    'number_of_replicas' => 5,
                ],
            ],
        ];

        yield 'with null of shards' => [
            [
                'index' => [
                    'number_of_shards' => null,
                    'number_of_replicas' => null,
                ],
            ],
            [
                'index' => [
                ],
            ],
        ];

        yield 'with null of shards with additional field' => [
            [
                'index' => [
                    'number_of_shards' => null,
                    'number_of_replicas' => null,
                    'test' => 1,
                ],
            ],
            [
                'index' => [
                    'test' => 1,
                ],
            ],
        ];
    }

    /**
     * Mirrors the analyzer/char_filter shape of the bundled `elasticsearch.yaml`
     * `product.analysis` section enough to exercise the IndexCreator logic.
     *
     * @return array<string, mixed>
     */
    private static function technicalTermAnalysisFixture(): array
    {
        return [
            'char_filter' => [
                'sw_decimal_normalize' => ['type' => 'pattern_replace', 'pattern' => '(\d),(\d)', 'replacement' => '$1.$2'],
                'sw_dimension_normalize' => ['type' => 'pattern_replace', 'pattern' => '(\d)\s*[xX×]\s*(\d)', 'replacement' => '$1x$2'],
                'sw_unit_glue' => ['type' => 'pattern_replace', 'pattern' => '(^|\s)(\d+(?:[./,\'\-]\d+)*)\s+([^\d\s])', 'replacement' => '$1$2$3'],
            ],
            'analyzer' => [
                'sw_whitespace_analyzer' => ['type' => 'custom', 'tokenizer' => 'whitespace', 'filter' => ['lowercase']],
                'sw_ngram_analyzer' => ['type' => 'custom', 'tokenizer' => 'whitespace', 'filter' => ['lowercase', 'sw_ngram_filter']],
                'sw_whitespace_technical_term_index_analyzer' => ['type' => 'custom', 'tokenizer' => 'whitespace', 'char_filter' => ['sw_unit_glue'], 'filter' => ['sw_word_delimiter_filter']],
                'sw_whitespace_technical_term_search_analyzer' => ['type' => 'custom', 'tokenizer' => 'whitespace', 'char_filter' => ['sw_unit_glue'], 'filter' => ['sw_word_delimiter_filter']],
                'sw_english_technical_term_index_analyzer' => ['type' => 'custom', 'tokenizer' => 'whitespace', 'char_filter' => ['sw_unit_glue'], 'filter' => ['sw_word_delimiter_filter']],
                'sw_english_technical_term_search_analyzer' => ['type' => 'custom', 'tokenizer' => 'whitespace', 'char_filter' => ['sw_unit_glue'], 'filter' => ['sw_word_delimiter_filter']],
                'sw_german_technical_term_index_analyzer' => ['type' => 'custom', 'tokenizer' => 'whitespace', 'char_filter' => ['sw_decimal_normalize', 'sw_unit_glue'], 'filter' => ['sw_word_delimiter_filter']],
                'sw_german_technical_term_search_analyzer' => ['type' => 'custom', 'tokenizer' => 'whitespace', 'char_filter' => ['sw_decimal_normalize', 'sw_unit_glue'], 'filter' => ['sw_word_delimiter_filter']],
            ],
        ];
    }
}
