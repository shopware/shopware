<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Product;

use Doctrine\DBAL\Connection;
use OpenSearchDSL\Query\Compound\BoolQuery;
use OpenSearchDSL\Query\FullText\MatchQuery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\System\CustomField\CustomFieldTypes;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Elasticsearch\Framework\AbstractElasticsearchDefinition;
use Shopware\Elasticsearch\Framework\ElasticsearchFieldBuilder;
use Shopware\Elasticsearch\Framework\ElasticsearchFieldMapper;
use Shopware\Elasticsearch\Framework\ElasticsearchIndexingUtils;
use Shopware\Elasticsearch\Product\ElasticsearchProductDefinition;
use Shopware\Elasticsearch\Product\ProductSearchQueryBuilder;
use Shopware\Tests\Unit\Core\System\Language\Stubs\StaticLanguageLoader;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(ElasticsearchProductDefinition::class)]
class ElasticsearchProductDefinitionTest extends TestCase
{
    private const TRANSLATABLE_SEARCHABLE_MAPPING = [
        'properties' => [
            'lang_en' => [
                'type' => 'keyword',
                'ignore_above' => 10000,
                'normalizer' => 'sw_lowercase_normalizer',
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
            'lang_de' => [
                'type' => 'keyword',
                'ignore_above' => 10000,
                'normalizer' => 'sw_lowercase_normalizer',
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
        ],
    ];

    private const SEARCHABLE_MAPPING = [
        'type' => 'keyword',
        'ignore_above' => 10000,
        'normalizer' => 'sw_lowercase_normalizer',
        'fields' => [
            'search' => [
                'type' => 'text',
                'analyzer' => 'sw_whitespace_analyzer',
            ],
            'ngram' => [
                'type' => 'text',
                'analyzer' => 'sw_ngram_analyzer',
            ],
        ],
    ];

    private readonly IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
    }

    public function testMapping(): void
    {
        $languageLoader = new StaticLanguageLoader([
            'lang_en' => [
                'id' => 'lang_en',
                'parentId' => 'parentId',
                'code' => 'en-GB',
            ],
            'lang_de' => [
                'id' => 'lang_de',
                'parentId' => 'parentId',
                'code' => 'de-DE',
            ],
        ]);

        $parameterBag = new ParameterBag([
            'elasticsearch.product.custom_fields_mapping' => [
                'bool' => CustomFieldTypes::BOOL,
                'int' => CustomFieldTypes::INT,
            ],
        ]);

        $connection = $this->createMock(Connection::class);

        $utils = new ElasticsearchIndexingUtils($connection, new EventDispatcher(), $parameterBag);
        $fieldBuilder = new ElasticsearchFieldBuilder($languageLoader, $utils, [
            'en' => 'sw_english_analyzer',
            'de' => 'sw_german_analyzer',
        ]);
        $fieldMapper = new ElasticsearchFieldMapper($utils);

        $definition = new ElasticsearchProductDefinition(
            $this->createMock(ProductDefinition::class),
            $connection,
            $this->createMock(ProductSearchQueryBuilder::class),
            $fieldBuilder,
            $fieldMapper,
            false,
            'dev'
        );

        $expectedMapping = [
            'properties' => [
                'id' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
                'parentId' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
                'categoryTree' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
                'categoryIds' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
                'propertyIds' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
                'optionIds' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
                'tagIds' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
                'active' => [
                    'type' => 'boolean',
                ],
                'available' => [
                    'type' => 'boolean',
                ],
                'isCloseout' => [
                    'type' => 'boolean',
                ],
                'categoriesRo' => [
                    'type' => 'nested',
                    'properties' => [
                        'id' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
                        '_count' => [
                            'type' => 'long',
                        ],
                    ],
                ],
                'categories' => [
                    'type' => 'nested',
                    'properties' => [
                        'id' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
                        'name' => self::TRANSLATABLE_SEARCHABLE_MAPPING,
                        '_count' => [
                            'type' => 'long',
                        ],
                    ],
                ],
                'childCount' => [
                    'type' => 'long',
                ],
                'autoIncrement' => [
                    'type' => 'long',
                ],
                'manufacturerNumber' => self::SEARCHABLE_MAPPING,
                'description' => self::TRANSLATABLE_SEARCHABLE_MAPPING,
                'metaTitle' => self::TRANSLATABLE_SEARCHABLE_MAPPING,
                'metaDescription' => self::TRANSLATABLE_SEARCHABLE_MAPPING,
                'displayGroup' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
                'ean' => self::SEARCHABLE_MAPPING,
                'height' => [
                    'type' => 'double',
                ],
                'length' => [
                    'type' => 'double',
                ],
                'manufacturer' => [
                    'type' => 'nested',
                    'properties' => [
                        'id' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
                        'name' => self::TRANSLATABLE_SEARCHABLE_MAPPING,
                        '_count' => [
                            'type' => 'long',
                        ],
                    ],
                ],
                'markAsTopseller' => [
                    'type' => 'boolean',
                ],
                'name' => self::TRANSLATABLE_SEARCHABLE_MAPPING,
                'options' => [
                    'type' => 'nested',
                    'properties' => [
                        'id' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
                        'name' => self::TRANSLATABLE_SEARCHABLE_MAPPING,
                        'groupId' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
                        '_count' => [
                            'type' => 'long',
                        ],
                    ],
                ],
                'productNumber' => self::SEARCHABLE_MAPPING,
                'properties' => [
                    'type' => 'nested',
                    'properties' => [
                        'id' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
                        'name' => self::TRANSLATABLE_SEARCHABLE_MAPPING,
                        'groupId' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
                        'group' => [
                            'type' => 'nested',
                            'properties' => [
                                'id' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
                                '_count' => [
                                    'type' => 'long',
                                ],
                            ],
                        ],
                        '_count' => [
                            'type' => 'long',
                        ],
                    ],
                ],
                'ratingAverage' => [
                    'type' => 'double',
                ],
                'releaseDate' => [
                    'type' => 'date',
                    'format' => 'yyyy-MM-dd HH:mm:ss.000||strict_date_optional_time||epoch_millis',
                    'ignore_malformed' => true,
                ],
                'createdAt' => [
                    'type' => 'date',
                    'format' => 'yyyy-MM-dd HH:mm:ss.000||strict_date_optional_time||epoch_millis',
                    'ignore_malformed' => true,
                ],
                'sales' => [
                    'type' => 'long',
                ],
                'stock' => [
                    'type' => 'long',
                ],
                'availableStock' => [
                    'type' => 'long',
                ],
                'shippingFree' => [
                    'type' => 'boolean',
                ],
                'taxId' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
                'tags' => [
                    'type' => 'nested',
                    'properties' => [
                        'id' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
                        'name' => self::SEARCHABLE_MAPPING,
                        '_count' => [
                            'type' => 'long',
                        ],
                    ],
                ],
                'visibilities' => [
                    'type' => 'nested',
                    'properties' => [
                        'salesChannelId' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
                        'visibility' => [
                            'type' => 'long',
                        ],
                        '_count' => [
                            'type' => 'long',
                        ],
                    ],
                ],
                'coverId' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
                'weight' => [
                    'type' => 'double',
                ],
                'width' => [
                    'type' => 'double',
                ],
                'customFields' => [
                    'properties' => [
                        'lang_en' => [
                            'type' => 'object',
                            'dynamic' => true,
                            'properties' => [],
                        ],
                        'lang_de' => [
                            'type' => 'object',
                            'dynamic' => true,
                            'properties' => [],
                        ],
                    ],
                ],
                'customSearchKeywords' => self::TRANSLATABLE_SEARCHABLE_MAPPING,
                'states' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
                'manufacturerId' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            ],
            'dynamic_templates' => [
                ['cheapest_price' => [
                    'match' => 'cheapest_price_rule*',
                    'mapping' => [
                        'type' => 'double',
                    ],
                ],
                ],
                ['price_percentage' => [
                    'path_match' => 'price.*.percentage.*',
                    'mapping' => [
                        'type' => 'double',
                    ],
                ],
                ],
                [
                    'long_to_double' => [
                        'match_mapping_type' => 'long',
                        'mapping' => [
                            'type' => 'double',
                        ],
                    ],
                ],
            ],
        ];

        static::assertEquals($expectedMapping, $definition->getMapping(Context::createDefaultContext()));
    }

    public function testMappingCustomFields(): void
    {
        $connection = $this->createMock(Connection::class);

        $languageLoader = new StaticLanguageLoader([
            'lang_en' => [
                'id' => 'lang_en',
                'parentId' => 'parentId',
                'code' => 'en-GB',
            ],
            'lang_de' => [
                'id' => 'lang_de',
                'parentId' => 'parentId',
                'code' => 'de-DE',
            ],
        ]);

        $parameterBag = new ParameterBag([
            'elasticsearch.product.custom_fields_mapping' => [
                'bool' => CustomFieldTypes::BOOL,
                'int' => CustomFieldTypes::INT,
                'test1' => CustomFieldTypes::TEXT,
                'test2' => 'unknown',
            ],
        ]);

        $instanceRegistry = $this->getDefinitionRegistry();

        $utils = new ElasticsearchIndexingUtils($connection, new EventDispatcher(), $parameterBag);
        $fieldBuilder = new ElasticsearchFieldBuilder($languageLoader, $utils, []);
        $fieldMapper = new ElasticsearchFieldMapper($utils);

        $definition = new ElasticsearchProductDefinition(
            $instanceRegistry->get(ProductDefinition::class),
            $connection,
            $this->createMock(ProductSearchQueryBuilder::class),
            $fieldBuilder,
            $fieldMapper,
            false,
            'dev'
        );

        $mapping = $definition->getMapping(Context::createDefaultContext());

        $customFields = $mapping['properties']['customFields'];

        static::assertArrayHasKey('lang_en', $customFields['properties']);
        static::assertArrayHasKey('lang_de', $customFields['properties']);
        static::assertArrayHasKey('properties', $customFields['properties']['lang_en']);
        static::assertArrayHasKey('properties', $customFields['properties']['lang_de']);
        static::assertArrayHasKey('test1', $customFields['properties']['lang_en']['properties']);
        static::assertArrayHasKey('test1', $customFields['properties']['lang_de']['properties']);
        static::assertSame(
            [
                'type' => 'keyword',
                'ignore_above' => 10000,
                'normalizer' => 'sw_lowercase_normalizer',
                'fields' => [
                    'search' => [
                        'type' => 'text',
                        'analyzer' => 'sw_whitespace_analyzer',
                    ],
                    'ngram' => ['type' => 'text', 'analyzer' => 'sw_ngram_analyzer'],
                ],
            ],
            $customFields['properties']['lang_en']['properties']['test1']
        );
        static::assertSame(
            [
                'type' => 'keyword',
                'ignore_above' => 10000,
                'normalizer' => 'sw_lowercase_normalizer',
                'fields' => [
                    'search' => [
                        'type' => 'text',
                        'analyzer' => 'sw_whitespace_analyzer',
                    ],
                    'ngram' => ['type' => 'text', 'analyzer' => 'sw_ngram_analyzer'],
                ],
            ],
            $customFields['properties']['lang_de']['properties']['test1']
        );

        static::assertArrayHasKey('test2', $customFields['properties']['lang_en']['properties']);
        static::assertArrayHasKey('test2', $customFields['properties']['lang_de']['properties']);
        static::assertSame(
            [
                'type' => 'keyword',
                'ignore_above' => 10000,
                'normalizer' => 'sw_lowercase_normalizer',
                'fields' => [
                    'search' => [
                        'type' => 'text',
                        'analyzer' => 'sw_whitespace_analyzer',
                    ],
                    'ngram' => ['type' => 'text', 'analyzer' => 'sw_ngram_analyzer'],
                ],
            ],
            $customFields['properties']['lang_en']['properties']['test2']
        );
        static::assertSame(
            [
                'type' => 'keyword',
                'ignore_above' => 10000,
                'normalizer' => 'sw_lowercase_normalizer',
                'fields' => [
                    'search' => [
                        'type' => 'text',
                        'analyzer' => 'sw_whitespace_analyzer',
                    ],
                    'ngram' => ['type' => 'text', 'analyzer' => 'sw_ngram_analyzer'],
                ],
            ],
            $customFields['properties']['lang_de']['properties']['test2']
        );
    }

    public function testGetDefinition(): void
    {
        $registry = $this->getDefinitionRegistry();

        $definition = $registry->get(ProductDefinition::class);

        static::assertInstanceOf(ProductDefinition::class, $definition);

        $esDefinition = new ElasticsearchProductDefinition(
            $definition,
            $this->createMock(Connection::class),
            $this->createMock(ProductSearchQueryBuilder::class),
            $this->createMock(ElasticsearchFieldBuilder::class),
            $this->createMock(ElasticsearchFieldMapper::class),
            false,
            'dev'
        );

        static::assertSame($definition, $esDefinition->getEntityDefinition());
    }

    public function testBuildTermQueryUsingSearchQueryBuilder(): void
    {
        $searchQueryBuilder = $this->createMock(ProductSearchQueryBuilder::class);
        $boolQuery = new BoolQuery();
        $boolQuery->add(new MatchQuery('name', 'test'));
        $searchQueryBuilder
            ->method('build')
            ->willReturn($boolQuery);

        $registry = $this->getDefinitionRegistry();
        $definition = $registry->get(ProductDefinition::class);
        static::assertInstanceOf(ProductDefinition::class, $definition);

        $utils = new ElasticsearchIndexingUtils($this->createMock(Connection::class), new EventDispatcher(), new ParameterBag([]));
        $fieldBuilder = new ElasticsearchFieldBuilder(new StaticLanguageLoader([]), $utils, []);
        $fieldMapper = new ElasticsearchFieldMapper($utils);

        $definition = new ElasticsearchProductDefinition(
            $definition,
            $this->createMock(Connection::class),
            $searchQueryBuilder,
            $fieldBuilder,
            $fieldMapper,
            false,
            'dev'
        );

        $criteria = new Criteria();
        $criteria->setTerm('test');
        $query = $definition->buildTermQuery(Context::createDefaultContext(), $criteria);

        $queries = $query->toArray();

        static::assertEquals([
            'match' => [
                'name' => [
                    'query' => 'test',
                ],
            ],
        ], $queries);
    }

    public function testFetching(): void
    {
        $registry = $this->getDefinitionRegistry();
        $definition = $registry->get(ProductDefinition::class);
        static::assertInstanceOf(ProductDefinition::class, $definition);

        $connection = $this->getConnection();
        $definition = new ElasticsearchProductDefinition(
            $definition,
            $connection,
            $this->createMock(ProductSearchQueryBuilder::class),
            $this->createMock(ElasticsearchFieldBuilder::class),
            $this->createMock(ElasticsearchFieldMapper::class),
            false,
            'dev'
        );

        $uuid = $this->ids->get('product-1');
        $documents = $definition->fetch([$uuid], Context::createDefaultContext());
        static::assertArrayHasKey($uuid, $documents);

        $document = $documents[$uuid];

        static::assertSame($uuid, $document['id']);
        static::assertArrayHasKey('name', $document);
        static::assertArrayHasKey(Defaults::LANGUAGE_SYSTEM, $document['name']);
        static::assertSame('Test', $document['name'][Defaults::LANGUAGE_SYSTEM]);

        $prices = [
            'cheapest_price_rule-1_b7d2554b0ce847cd82f3ac9bd1c0dfca_gross' => 5,
            'cheapest_price_rule-1_b7d2554b0ce847cd82f3ac9bd1c0dfca_net' => 4,
            'cheapest_price_rule-1_b7d2554b0ce847cd82f3ac9bd1c0dfc2_gross' => 5,
            'cheapest_price_rule-1_b7d2554b0ce847cd82f3ac9bd1c0dfc2_net' => 4,
            'cheapest_price_rule-1_b7d2554b0ce847cd82f3ac9bd1c0dfc2_gross_percentage' => 1,
            'cheapest_price_rule-1_b7d2554b0ce847cd82f3ac9bd1c0dfc2_net_percentage' => 2,
        ];

        foreach ($prices as $key => $price) {
            static::assertArrayHasKey($key, $document);

            static::assertSame($price, $document[$key]);
        }

        static::assertSame(
            [
                '809c1844f4734243b6aa04aba860cd45',
                'e4a08f9dd88f4a228240de7107e4ae4b',
            ],
            $document['propertyIds']
        );

        static::assertArrayHasKey('visibilities', $document);
        static::assertSame(
            [
                [
                    '_count' => 1,
                    'visibility' => 20,
                    'salesChannelId' => 'sc-2',
                ],
                [
                    '_count' => 1,
                    'visibility' => 20,
                    'salesChannelId' => 'sc-2',
                ],
                [
                    '_count' => 1,
                    'visibility' => 20,
                    'salesChannelId' => 'sc-2',
                ],
                [
                    '_count' => 1,
                    'visibility' => 30,
                    'salesChannelId' => 'sc-1',
                ],
                [
                    '_count' => 1,
                    'visibility' => 30,
                    'salesChannelId' => 'sc-1',
                ],
                [
                    '_count' => 1,
                    'visibility' => 20,
                    'salesChannelId' => 'sc-2',
                ],
            ],
            $document['visibilities']
        );

        static::assertSame(
            [
                [
                    'id' => '809c1844f4734243b6aa04aba860cd45',
                    '_count' => 1,
                    'groupId' => 'a73b9355da654243b92ce16c63e9b6cd',
                    'group' => [
                        'id' => 'a73b9355da654243b92ce16c63e9b6cd',
                        '_count' => 1,
                    ],
                    'name' => [
                        Defaults::LANGUAGE_SYSTEM => 'Property A',
                    ],
                ],
                [
                    'id' => 'e4a08f9dd88f4a228240de7107e4ae4b',
                    '_count' => 1,
                    'groupId' => 'a73b9355da654243b92ce16c63e9b6cd',
                    'group' => [
                        'id' => 'a73b9355da654243b92ce16c63e9b6cd',
                        '_count' => 1,
                    ],
                    'name' => [
                        Defaults::LANGUAGE_SYSTEM => 'Property B',
                    ],
                ],
            ],
            $document['properties']
        );
    }

    public function testFetchFormatsCustomFieldsAndRemovesNotMappedFields(): void
    {
        $connection = $this->getConnection();

        $languageLoader = new StaticLanguageLoader([
            Defaults::LANGUAGE_SYSTEM => [
                'id' => Defaults::LANGUAGE_SYSTEM,
                'parentId' => 'parentId',
                'code' => 'en-GB',
            ],
        ]);

        $parameterBag = new ParameterBag([
            'elasticsearch.product.custom_fields_mapping' => ['bool' => CustomFieldTypes::BOOL, 'int' => CustomFieldTypes::INT],
        ]);

        $instanceRegistry = $this->getDefinitionRegistry();

        $utils = new ElasticsearchIndexingUtils($connection, new EventDispatcher(), $parameterBag);
        $fieldBuilder = new ElasticsearchFieldBuilder($languageLoader, $utils, []);
        $fieldMapper = new ElasticsearchFieldMapper($utils);

        $definition = new ElasticsearchProductDefinition(
            $instanceRegistry->get(ProductDefinition::class),
            $connection,
            $this->createMock(ProductSearchQueryBuilder::class),
            $fieldBuilder,
            $fieldMapper,
            false,
            'dev'
        );

        $uuid = $this->ids->get('product-1');
        $documents = $definition->fetch([$uuid], Context::createDefaultContext());

        static::assertArrayHasKey($uuid, $documents);
        static::assertArrayHasKey('customFields', $documents[$uuid]);
        static::assertArrayHasKey(Defaults::LANGUAGE_SYSTEM, $documents[$uuid]['customFields']);
        static::assertArrayHasKey('bool', $documents[$uuid]['customFields'][Defaults::LANGUAGE_SYSTEM]);
        static::assertIsBool($documents[$uuid]['customFields'][Defaults::LANGUAGE_SYSTEM]['bool']);
        static::assertArrayHasKey('int', $documents[$uuid]['customFields'][Defaults::LANGUAGE_SYSTEM]);
        static::assertIsFloat($documents[$uuid]['customFields'][Defaults::LANGUAGE_SYSTEM]['int']);
        static::assertArrayNotHasKey('unknown', $documents[$uuid]['customFields'][Defaults::LANGUAGE_SYSTEM]);
    }

    public function getConnection(): MockObject&Connection
    {
        $connection = $this->createMock(Connection::class);

        $connection
            ->method('fetchAllAssociativeIndexed')
            ->willReturnOnConsecutiveCalls(
                [
                    $this->ids->get('product-1') => [
                        'id' => $this->ids->get('product-1'),
                        'parentId' => null,
                        'productNumber' => 1,
                        'autoIncrement' => 1,
                        'ean' => '',
                        'active' => true,
                        'available' => true,
                        'isCloseout' => true,
                        'shippingFree' => true,
                        'markAsTopseller' => true,
                        'availableStock' => 5,
                        'translation' => '[{"languageId": null, "name": null}, {"languageId": "2fbb5fe2e29a4d70aa5854ce7ce3e20b", "name": "Test", "customFields": {"bool": "1", "int": 2, "unknown": "foo"}}]',
                        'translation_parent' => '{}',
                        'manufacturer_translation' => '{}',
                        'tags' => '{}',
                        'categories' => '[{"id": null, "languageId": null, "name": null}, {"id": 1, "languageId": "2fbb5fe2e29a4d70aa5854ce7ce3e20b", "name": "Cat Test"}]',
                        'ratingAverage' => 4,
                        'sales' => 4,
                        'stock' => 4,
                        'weight' => 4,
                        'width' => 4,
                        'height' => 4,
                        'length' => 4,
                        'productManufacturerId' => null,
                        'manufacturerNumber' => null,
                        'taxId' => 'tax',
                        'displayGroup' => '1',
                        'coverId' => null,
                        'childCount' => 0,
                        'cheapest_price_accessor' => '{"rule-1": {"b7d2554b0ce847cd82f3ac9bd1c0dfca": {"gross": 5, "net": 4}, "b7d2554b0ce847cd82f3ac9bd1c0dfc2": {"gross": 5, "net": 4, "percentage": {"gross": 1, "net": 2}}}}',
                        'visibilities' => '[{"visibility": 20, "salesChannelId": "sc-2"}, {"visibility": 20, "salesChannelId": "sc-2"}, {"visibility": 20, "salesChannelId": "sc-2"}, {"visibility": 30, "salesChannelId": "sc-1"}, {"visibility": 30, "salesChannelId": "sc-1"}, {"visibility": 20, "salesChannelId": "sc-2"}]',
                        'propertyIds' => '["809c1844f4734243b6aa04aba860cd45", "e4a08f9dd88f4a228240de7107e4ae4b"]',
                        'optionIds' => '["809c1844f4734243b6aa04aba860cd45", "e4a08f9dd88f4a228240de7107e4ae4b"]',
                    ],
                ],
                [
                    '809c1844f4734243b6aa04aba860cd45' => [
                        'id' => '809c1844f4734243b6aa04aba860cd45',
                        'groupId' => 'a73b9355da654243b92ce16c63e9b6cd',
                        'group' => [
                            'id' => 'a73b9355da654243b92ce16c63e9b6cd',
                        ],
                        'translations' => json_encode([
                            [
                                'languageId' => '2fbb5fe2e29a4d70aa5854ce7ce3e20b',
                                'name' => 'Property A',
                            ],
                        ]),
                    ],
                    'e4a08f9dd88f4a228240de7107e4ae4b' => [
                        'id' => 'e4a08f9dd88f4a228240de7107e4ae4b',
                        'groupId' => 'a73b9355da654243b92ce16c63e9b6cd',
                        'group' => [
                            'id' => 'a73b9355da654243b92ce16c63e9b6cd',
                        ],
                        'translations' => json_encode([
                            [
                                'languageId' => '2fbb5fe2e29a4d70aa5854ce7ce3e20b',
                                'name' => 'Property B',
                            ],
                        ]),
                    ],
                ],
            );

        return $connection;
    }

    private function getDefinitionRegistry(): DefinitionInstanceRegistry
    {
        return new StaticDefinitionInstanceRegistry(
            [
                ProductDefinition::class,
                ProductTranslationDefinition::class,
            ],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
        );
    }
}
