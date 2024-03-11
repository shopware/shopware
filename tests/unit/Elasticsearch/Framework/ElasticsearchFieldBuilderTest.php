<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Framework;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Elasticsearch\Framework\AbstractElasticsearchDefinition;
use Shopware\Elasticsearch\Framework\ElasticsearchFieldBuilder;
use Shopware\Elasticsearch\Framework\ElasticsearchIndexingUtils;
use Shopware\Tests\Unit\Core\System\Language\Stubs\StaticLanguageLoader;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[CoversClass(ElasticsearchFieldBuilder::class)]
class ElasticsearchFieldBuilderTest extends TestCase
{
    public function testBuildTranslatedField(): void
    {
        $deLanguageId = Uuid::randomHex();
        $enLanguageId = Uuid::randomHex();
        $enInheritedLanguageId = Uuid::randomHex();

        $languageLoader = new StaticLanguageLoader([
            $deLanguageId => [
                'id' => $deLanguageId,
                'parentId' => 'parentId',
                'code' => 'de-DE',
            ],
            $enLanguageId => [
                'id' => $enLanguageId,
                'parentId' => 'parentId',
                'code' => 'en-GB',
            ],
            $enInheritedLanguageId => [
                'id' => $enLanguageId,
                'parentId' => 'parentId',
                'code' => 'en-GB',
                'parentCode' => 'en-GB',
            ],
        ]);

        $dispatcher = new EventDispatcher();
        $parameterBag = new ParameterBag(['elasticsearch.product.custom_fields_mapping' => [
            'cf_foo' => 'text',
            'cf_baz' => 'int',
        ]]);

        $connection = $this->createMock(Connection::class);

        $utils = new ElasticsearchIndexingUtils(
            $connection,
            $dispatcher,
            $parameterBag,
        );

        $builder = new ElasticsearchFieldBuilder($languageLoader, $utils, [
            'en' => 'sw_english_analyzer',
            'de' => 'sw_german_analyzer',
        ]);

        $result = $builder->translated(AbstractElasticsearchDefinition::SEARCH_FIELD);

        static::assertSame([
            'properties' => [
                $deLanguageId => [
                    'fields' => [
                        'search' => [
                            'type' => 'text',
                            'analyzer' => 'sw_german_analyzer',
                        ],
                        'ngram' => [
                            'type' => 'text',
                            'analyzer' => 'sw_ngram_analyzer',
                        ],
                    ],
                ],
                $enLanguageId => [
                    'fields' => [
                        'search' => [
                            'type' => 'text',
                            'analyzer' => 'sw_english_analyzer',
                        ],
                        'ngram' => [
                            'type' => 'text',
                            'analyzer' => 'sw_ngram_analyzer',
                        ],
                    ],
                ],
                $enInheritedLanguageId => [
                    'fields' => [
                        'search' => [
                            'type' => 'text',
                            'analyzer' => 'sw_english_analyzer',
                        ],
                        'ngram' => [
                            'type' => 'text',
                            'analyzer' => 'sw_ngram_analyzer',
                        ],
                    ],
                ],
            ],
        ], $result);
    }

    public function testBuildTranslatedCustomFields(): void
    {
        $deLanguageId = Uuid::randomHex();
        $enLanguageId = Uuid::randomHex();

        $languageLoader = new StaticLanguageLoader([
            $deLanguageId => [
                'id' => $deLanguageId,
                'parentId' => 'parentId',
                'code' => 'de-DE',
            ],
            $enLanguageId => [
                'id' => $enLanguageId,
                'parentId' => 'parentId',
                'code' => 'en-GB',
            ],
        ]);

        $dispatcher = new EventDispatcher();
        $parameterBag = new ParameterBag(['elasticsearch.product.custom_fields_mapping' => [
            'cf_foo' => 'text',
            'cf_baz' => 'int',
        ]]);

        $connection = $this->createMock(Connection::class);
        $connection->expects(static::once())->method('fetchAllKeyValue')->willReturn([
            'cf_bool' => 'bool',
        ]);

        $utils = new ElasticsearchIndexingUtils(
            $connection,
            $dispatcher,
            $parameterBag,
        );

        $builder = new ElasticsearchFieldBuilder($languageLoader, $utils, []);

        $result = $builder->customFields('product', new Context(new SystemSource()));

        static::assertSame([
            'properties' => [
                $deLanguageId => [
                    'type' => 'object',
                    'dynamic' => true,
                    'properties' => [
                        'cf_bool' => [
                            'type' => 'boolean',
                        ],
                        'cf_foo' => [
                            'type' => 'text',
                        ],
                        'cf_baz' => [
                            'type' => 'long',
                        ],
                    ],
                ],
                $enLanguageId => [
                    'type' => 'object',
                    'dynamic' => true,
                    'properties' => [
                        'cf_bool' => [
                            'type' => 'boolean',
                        ],
                        'cf_foo' => [
                            'type' => 'text',
                        ],
                        'cf_baz' => [
                            'type' => 'long',
                        ],
                    ],
                ],
            ],
        ], $result);
    }

    public function testBuildDatetimeField(): void
    {
        $dateTimeField = ElasticsearchFieldBuilder::datetime(['properties' => [
            'foo' => [
                'type' => 'text',
            ],
        ]]);

        static::assertEquals([
            'type' => 'date',
            'format' => 'yyyy-MM-dd HH:mm:ss.000||strict_date_optional_time||epoch_millis',
            'ignore_malformed' => true,
            'properties' => [
                'foo' => [
                    'type' => 'text',
                ],
            ],
        ], $dateTimeField);
    }

    public function testBuildNestedField(): void
    {
        $nestedFields = ElasticsearchFieldBuilder::nested(['name' => AbstractElasticsearchDefinition::KEYWORD_FIELD + AbstractElasticsearchDefinition::SEARCH_FIELD]);

        static::assertEquals([
            'type' => 'nested',
            'properties' => [
                'id' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
                '_count' => AbstractElasticsearchDefinition::INT_FIELD,
                'name' => [
                    'type' => 'keyword',
                    'normalizer' => 'sw_lowercase_normalizer',
                    'fields' => [
                        'search' => ['type' => 'text'],
                        'ngram' => ['type' => 'text', 'analyzer' => 'sw_ngram_analyzer'],
                    ],
                ],
            ],
        ], $nestedFields);
    }
}
