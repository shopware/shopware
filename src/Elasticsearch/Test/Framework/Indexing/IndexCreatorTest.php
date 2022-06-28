<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Test\Framework\Indexing;

use Elasticsearch\Client;
use Elasticsearch\Namespaces\IndicesNamespace;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Elasticsearch\Framework\Indexing\IndexCreator;
use Shopware\Elasticsearch\Product\ElasticsearchProductDefinition;

/**
 * @covers \Shopware\Elasticsearch\Framework\Indexing\IndexCreator
 *
 * @internal
 */
class IndexCreatorTest extends TestCase
{
    /**
     * @dataProvider providerCreateIndices
     */
    public function testIndexCreation(array $constructorConfig, array $expectedConfig): void
    {
        $client = $this->createMock(Client::class);
        $indices = $this->createMock(IndicesNamespace::class);
        $indices
            ->expects(static::once())
            ->method('create')
            ->with([
                'index' => 'foo',
                'body' => [
                    'settings' => $expectedConfig,
                    'mappings' => [
                        'properties' => [
                            'fullText' => [
                                'type' => 'text',
                                'fields' => [
                                    'ngram' => ['type' => 'text', 'analyzer' => 'sw_ngram_analyzer'],
                                ],
                            ],
                            'fullTextBoosted' => ['type' => 'text'],
                        ],
                    ],
                ],
            ]);

        // Alias does not exists, swap directly
        $indices->expects(static::once())->method('existsAlias')->with(['name' => 'bla'])->willReturn(false);
        $indices->expects(static::once())->method('refresh')->with(['index' => 'foo']);
        $indices->expects(static::once())->method('putAlias')->with(['index' => 'foo', 'name' => 'bla']);

        $client
            ->method('indices')
            ->willReturn($indices);

        $index = new IndexCreator(
            $client,
            [
                'settings' => $constructorConfig,
            ],
            []
        );

        $definition = $this->createMock(ElasticsearchProductDefinition::class);
        $index->createIndex($definition, 'foo', 'bla', Context::createDefaultContext());
    }

    public function testCreateIndexWithSourceField(): void
    {
        $client = $this->createMock(Client::class);
        $indices = $this->createMock(IndicesNamespace::class);
        $indices
            ->expects(static::once())
            ->method('create')
            ->with([
                'index' => 'foo',
                'body' => [
                    'mappings' => [
                        'properties' => [
                            'fullText' => [
                                'type' => 'text',
                                'fields' => [
                                    'ngram' => ['type' => 'text', 'analyzer' => 'sw_ngram_analyzer'],
                                ],
                            ],
                            'fullTextBoosted' => ['type' => 'text'],
                        ],
                        '_source' => ['includes' => ['foo', 'fullText', 'fullTextBoosted']],
                    ],
                ],
            ]);

        $client
            ->method('indices')
            ->willReturn($indices);

        $index = new IndexCreator(
            $client,
            [],
            []
        );

        $definition = $this->createMock(ElasticsearchProductDefinition::class);
        $definition->method('getMapping')->willReturn([
            '_source' => [
                'includes' => ['foo'],
            ],
        ]);

        $index->createIndex($definition, 'foo', 'bla', Context::createDefaultContext());
    }

    public function testCreateIndexWithAliasExists(): void
    {
        $client = $this->createMock(Client::class);
        $indices = $this->createMock(IndicesNamespace::class);
        $indices
            ->expects(static::once())
            ->method('create')
            ->with([
                'index' => 'foo',
                'body' => [
                    'mappings' => [
                        'properties' => [
                            'fullText' => [
                                'type' => 'text',
                                'fields' => [
                                    'ngram' => ['type' => 'text', 'analyzer' => 'sw_ngram_analyzer'],
                                ],
                            ],
                            'fullTextBoosted' => ['type' => 'text'],
                        ],
                    ],
                ],
            ]);

        // Alias does not exists, swap directly
        $indices->expects(static::once())->method('existsAlias')->with(['name' => 'bla'])->willReturn(true);
        $indices->expects(static::never())->method('refresh');
        $indices->expects(static::never())->method('putAlias');

        $client
            ->method('indices')
            ->willReturn($indices);

        $index = new IndexCreator(
            $client,
            [],
            []
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

        $index = new IndexCreator(
            $client,
            [],
            []
        );

        static::assertTrue($index->aliasExists('foo'));
    }

    public function providerCreateIndices(): iterable
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
}
