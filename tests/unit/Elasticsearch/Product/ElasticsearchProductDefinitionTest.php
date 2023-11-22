<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Product;

use Doctrine\DBAL\Connection;
use OpenSearchDSL\Query\Compound\BoolQuery;
use OpenSearchDSL\Query\FullText\MatchQuery;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\CustomFieldTypes;
use Shopware\Elasticsearch\Framework\ElasticsearchFieldBuilder;
use Shopware\Elasticsearch\Framework\ElasticsearchFieldMapper;
use Shopware\Elasticsearch\Framework\ElasticsearchIndexingUtils;
use Shopware\Elasticsearch\Product\AbstractProductSearchQueryBuilder;
use Shopware\Elasticsearch\Product\ElasticsearchProductDefinition;
use Shopware\Elasticsearch\Product\EsProductDefinition;
use Shopware\Tests\Unit\Core\System\Language\Stubs\StaticLanguageLoader;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 *
 * @covers \Shopware\Elasticsearch\Product\ElasticsearchProductDefinition
 */
class ElasticsearchProductDefinitionTest extends TestCase
{
    private const SEARCHABLE_MAPPING = [
        'type' => 'keyword',
        'normalizer' => 'sw_lowercase_normalizer',
        'fields' => [
            'search' => [
                'type' => 'text',
            ],
            'ngram' => [
                'type' => 'text',
                'analyzer' => 'sw_ngram_analyzer',
            ],
        ],
    ];

    protected function setUp(): void
    {
        Feature::skipTestIfActive('ES_MULTILINGUAL_INDEX', $this);
    }

    public function testMapping(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(static::once())
            ->method('fetchAllKeyValue')
            ->willReturn(['test' => CustomFieldTypes::INT]);

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
            'en' => 'english',
            'de' => 'german',
        ]);
        $fieldMapper = new ElasticsearchFieldMapper($utils);

        $newImplementation = new EsProductDefinition(
            $this->createMock(ProductDefinition::class),
            $connection,
            $this->createMock(AbstractProductSearchQueryBuilder::class),
            $fieldBuilder,
            $fieldMapper
        );

        $definition = new ElasticsearchProductDefinition(
            $this->createMock(ProductDefinition::class),
            $connection,
            [],
            new EventDispatcher(),
            $this->createMock(AbstractProductSearchQueryBuilder::class),
            $newImplementation,
            false,
            'dev'
        );

        $expectedMapping = [
            '_source' => [
                'includes' => [
                    'id',
                    'autoIncrement',
                ],
            ],
            'properties' => [
                'id' => [
                    'type' => 'keyword',
                    'normalizer' => 'sw_lowercase_normalizer',
                ],
                'parentId' => [
                    'type' => 'keyword',
                    'normalizer' => 'sw_lowercase_normalizer',
                ],
                'categoryTree' => [
                    'type' => 'keyword',
                    'normalizer' => 'sw_lowercase_normalizer',
                ],
                'categoryIds' => [
                    'type' => 'keyword',
                    'normalizer' => 'sw_lowercase_normalizer',
                ],
                'propertyIds' => [
                    'type' => 'keyword',
                    'normalizer' => 'sw_lowercase_normalizer',
                ],
                'optionIds' => [
                    'type' => 'keyword',
                    'normalizer' => 'sw_lowercase_normalizer',
                ],
                'tagIds' => [
                    'type' => 'keyword',
                    'normalizer' => 'sw_lowercase_normalizer',
                ],
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
                        'id' => [
                            'type' => 'keyword',
                            'normalizer' => 'sw_lowercase_normalizer',
                        ],
                        '_count' => [
                            'type' => 'long',
                        ],
                    ],
                ],
                'categories' => [
                    'type' => 'nested',
                    'properties' => [
                        'id' => [
                            'type' => 'keyword',
                            'normalizer' => 'sw_lowercase_normalizer',
                        ],
                        'name' => self::SEARCHABLE_MAPPING,
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
                'description' => self::SEARCHABLE_MAPPING,
                'metaTitle' => self::SEARCHABLE_MAPPING,
                'metaDescription' => self::SEARCHABLE_MAPPING,
                'displayGroup' => [
                    'type' => 'keyword',
                    'normalizer' => 'sw_lowercase_normalizer',
                ],
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
                        'id' => [
                            'type' => 'keyword',
                            'normalizer' => 'sw_lowercase_normalizer',
                        ],
                        'name' => self::SEARCHABLE_MAPPING,
                        '_count' => [
                            'type' => 'long',
                        ],
                    ],
                ],
                'markAsTopseller' => [
                    'type' => 'boolean',
                ],
                'name' => self::SEARCHABLE_MAPPING,
                'options' => [
                    'type' => 'nested',
                    'properties' => [
                        'id' => [
                            'type' => 'keyword',
                            'normalizer' => 'sw_lowercase_normalizer',
                        ],
                        'name' => self::SEARCHABLE_MAPPING,
                        'groupId' => [
                            'type' => 'keyword',
                            'normalizer' => 'sw_lowercase_normalizer',
                        ],
                        '_count' => [
                            'type' => 'long',
                        ],
                    ],
                ],
                'productNumber' => self::SEARCHABLE_MAPPING,
                'properties' => [
                    'type' => 'nested',
                    'properties' => [
                        'id' => [
                            'type' => 'keyword',
                            'normalizer' => 'sw_lowercase_normalizer',
                        ],
                        'name' => self::SEARCHABLE_MAPPING,
                        'groupId' => [
                            'type' => 'keyword',
                            'normalizer' => 'sw_lowercase_normalizer',
                        ],
                        'group' => [
                            'type' => 'nested',
                            'properties' => [
                                'id' => [
                                    'type' => 'keyword',
                                    'normalizer' => 'sw_lowercase_normalizer',
                                ],
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
                'taxId' => [
                    'type' => 'keyword',
                    'normalizer' => 'sw_lowercase_normalizer',
                ],
                'tags' => [
                    'type' => 'nested',
                    'properties' => [
                        'id' => [
                            'type' => 'keyword',
                            'normalizer' => 'sw_lowercase_normalizer',
                        ],
                        'name' => self::SEARCHABLE_MAPPING,
                        '_count' => [
                            'type' => 'long',
                        ],
                    ],
                ],
                'visibilities' => [
                    'type' => 'nested',
                    'properties' => [
                        'salesChannelId' => [
                            'type' => 'keyword',
                            'normalizer' => 'sw_lowercase_normalizer',
                        ],
                        'visibility' => [
                            'type' => 'long',
                        ],
                        '_count' => [
                            'type' => 'long',
                        ],
                    ],
                ],
                'coverId' => [
                    'type' => 'keyword',
                    'normalizer' => 'sw_lowercase_normalizer',
                ],
                'weight' => [
                    'type' => 'double',
                ],
                'width' => [
                    'type' => 'double',
                ],
                'customFields' => [
                    'type' => 'object',
                    'dynamic' => true,
                    'properties' => [
                        'test' => [
                            'type' => 'long',
                        ],
                    ],
                ],
                'customSearchKeywords' => self::SEARCHABLE_MAPPING,
                'states' => [
                    'type' => 'keyword',
                    'normalizer' => 'sw_lowercase_normalizer',
                ],
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
        $connection->expects(static::once())
            ->method('fetchAllKeyValue')
            ->willReturn(['test' => CustomFieldTypes::INT]);

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
                'test1' => 'text',
                'test2' => 'unknown',
            ],
        ]);

        $connection = $this->createMock(Connection::class);

        $utils = new ElasticsearchIndexingUtils($connection, new EventDispatcher(), $parameterBag);
        $fieldBuilder = new ElasticsearchFieldBuilder($languageLoader, $utils, [
            'en' => 'english',
            'de' => 'german',
        ]);
        $fieldMapper = new ElasticsearchFieldMapper($utils);

        $newImplementation = new EsProductDefinition(
            $this->createMock(ProductDefinition::class),
            $connection,
            $this->createMock(AbstractProductSearchQueryBuilder::class),
            $fieldBuilder,
            $fieldMapper
        );

        $definition = new ElasticsearchProductDefinition(
            $this->createMock(ProductDefinition::class),
            $connection,
            [
                'test1' => 'text',
                'test2' => 'unknown',
            ],
            new EventDispatcher(),
            $this->createMock(AbstractProductSearchQueryBuilder::class),
            $newImplementation,
            false,
            'dev'
        );

        $mapping = $definition->getMapping(Context::createDefaultContext());

        $customFields = $mapping['properties']['customFields'];

        static::assertArrayHasKey('test', $customFields['properties']);
        static::assertSame(
            [
                'type' => 'long',
            ],
            $customFields['properties']['test']
        );

        static::assertArrayHasKey('test1', $customFields['properties']);
        static::assertSame(
            [
                'type' => 'text',
            ],
            $customFields['properties']['test1']
        );

        static::assertArrayHasKey('test2', $customFields['properties']);
        static::assertSame(
            [
                'type' => 'keyword',
            ],
            $customFields['properties']['test2']
        );
    }

    public function testGetDefinition(): void
    {
        $productDefinition = $this->createMock(ProductDefinition::class);
        $definition = new ElasticsearchProductDefinition(
            $productDefinition,
            $this->createMock(Connection::class),
            [],
            new EventDispatcher(),
            $this->createMock(AbstractProductSearchQueryBuilder::class),
            $this->createMock(EsProductDefinition::class),
            false,
            'dev'
        );

        static::assertSame($productDefinition, $definition->getEntityDefinition());
    }

    public function testBuildTermQueryUsingSearchQueryBuilder(): void
    {
        $searchQueryBuilder = $this->createMock(AbstractProductSearchQueryBuilder::class);
        $boolQuery = new BoolQuery();
        $boolQuery->add(new MatchQuery('name', 'test'));
        $searchQueryBuilder
            ->method('build')
            ->willReturn($boolQuery);

        $definition = new ElasticsearchProductDefinition(
            $this->createMock(ProductDefinition::class),
            $this->createMock(Connection::class),
            [],
            new EventDispatcher(),
            $searchQueryBuilder,
            $this->createMock(EsProductDefinition::class),
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
        $productId = Uuid::randomHex();

        $connection = $this->getConnection($productId);

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

        $parameterBag = new ParameterBag(['elasticsearch.product.custom_fields_mapping' => []]);

        $connection = $this->createMock(Connection::class);

        $utils = new ElasticsearchIndexingUtils($connection, new EventDispatcher(), $parameterBag);
        $fieldBuilder = new ElasticsearchFieldBuilder($languageLoader, $utils, [
            'en' => 'english',
            'de' => 'german',
        ]);
        $fieldMapper = new ElasticsearchFieldMapper($utils);

        $newImplementation = new EsProductDefinition(
            $this->createMock(ProductDefinition::class),
            $connection,
            $this->createMock(AbstractProductSearchQueryBuilder::class),
            $fieldBuilder,
            $fieldMapper
        );

        $definition = new ElasticsearchProductDefinition(
            $this->createMock(ProductDefinition::class),
            $connection,
            [],
            new EventDispatcher(),
            $this->createMock(AbstractProductSearchQueryBuilder::class),
            $newImplementation,
            false,
            'dev'
        );

        $documents = $definition->fetch([$productId], Context::createDefaultContext());

        static::assertArrayHasKey($productId, $documents);

        $document = $documents[$productId];

        static::assertSame($productId, $document['id']);
        static::assertSame('Test', $document['name']);

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
                    'visibility' => '20',
                    'salesChannelId' => 'sc-2',
                    '_count' => 1,
                ],
                [
                    'visibility' => '30',
                    'salesChannelId' => 'sc-1',
                    '_count' => 1,
                ],
            ],
            $document['visibilities']
        );

        static::assertSame(
            [
                [
                    'id' => '809c1844f4734243b6aa04aba860cd45',
                    'name' => 'Property A',
                    'groupId' => 'a73b9355da654243b92ce16c63e9b6cd',
                    'group' => [
                        'id' => 'a73b9355da654243b92ce16c63e9b6cd',
                        '_count' => 1,
                    ],
                    '_count' => 1,
                ],
                [
                    'id' => 'e4a08f9dd88f4a228240de7107e4ae4b',
                    'name' => 'Property B',
                    'groupId' => 'a73b9355da654243b92ce16c63e9b6cd',
                    'group' => [
                        'id' => 'a73b9355da654243b92ce16c63e9b6cd',
                        '_count' => 1,
                    ],
                    '_count' => 1,
                ],
            ],
            $document['properties']
        );
    }

    public function testFetchFormatsCustomFieldsAndRemovesNotMappedFields(): void
    {
        $productId = Uuid::randomHex();

        $connection = $this->getConnection($productId);

        $definition = new ElasticsearchProductDefinition(
            $this->createMock(ProductDefinition::class),
            $connection,
            ['bool' => CustomFieldTypes::BOOL, 'int' => CustomFieldTypes::INT],
            new EventDispatcher(),
            $this->createMock(AbstractProductSearchQueryBuilder::class),
            $this->createMock(EsProductDefinition::class),
            false,
            'dev'
        );

        $documents = $definition->fetch([$productId], Context::createDefaultContext());

        static::assertArrayHasKey($productId, $documents);
        static::assertArrayHasKey('customFields', $documents[$productId]);
        static::assertArrayHasKey('bool', $documents[$productId]['customFields']);
        static::assertIsBool($documents[$productId]['customFields']['bool']);
        static::assertArrayHasKey('int', $documents[$productId]['customFields']);
        static::assertIsFloat($documents[$productId]['customFields']['int']);
        static::assertArrayNotHasKey('unknown', $documents[$productId]['customFields']);
    }

    public function getConnection(string $uuid): Connection
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchAllAssociative')
            ->willReturnOnConsecutiveCalls(
                [
                    [
                        'id' => $uuid,
                        'parentId' => null,
                        'productNumber' => 1,
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
                        'visibilities' => '20,sc-2|20,sc-2|20,sc-2|30,sc-1|30,sc-1|20,sc-2',
                        'propertyIds' => '["809c1844f4734243b6aa04aba860cd45", "e4a08f9dd88f4a228240de7107e4ae4b"]',
                        'optionIds' => '["809c1844f4734243b6aa04aba860cd45", "e4a08f9dd88f4a228240de7107e4ae4b"]',
                    ],
                ],
            );

        $connection
            ->method('fetchAllAssociativeIndexed')
            ->willReturn(
                [
                    '809c1844f4734243b6aa04aba860cd45' => [
                        'id' => '809c1844f4734243b6aa04aba860cd45',
                        'property_group_id' => 'a73b9355da654243b92ce16c63e9b6cd',
                        'translations' => '[{"languageId": "2fbb5fe2e29a4d70aa5854ce7ce3e20b", "name": "Property A"}]',
                    ],
                    'e4a08f9dd88f4a228240de7107e4ae4b' => [
                        'id' => 'e4a08f9dd88f4a228240de7107e4ae4b',
                        'property_group_id' => 'a73b9355da654243b92ce16c63e9b6cd',
                        'translations' => '[{"languageId": "2fbb5fe2e29a4d70aa5854ce7ce3e20b", "name": "Property B"}]',
                    ],
                ]
            );

        return $connection;
    }
}
