<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Product;

use Doctrine\DBAL\Connection;
use OpenSearchDSL\Query\Compound\BoolQuery;
use OpenSearchDSL\Query\FullText\MatchQuery;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\System\CustomField\CustomFieldTypes;
use Shopware\Elasticsearch\Product\AbstractProductSearchQueryBuilder;
use Shopware\Elasticsearch\Product\EsProductDefinition;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 *
 * @covers \Shopware\Elasticsearch\Product\EsProductDefinition
 *
 * @deprecated tag:v6.6.0 - Will be removed, please transfer test cases to \Shopware\Tests\Unit\Elasticsearch\Product\ElasticsearchProductDefinitionTest
 */
class EsProductDefinitionTest extends TestCase
{
    private const TRANSLATABLE_SEARCHABLE_MAPPING = [
        'properties' => [
            'lang_en' => [
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
            ],
            'lang_de' => [
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
            ],
        ],
    ];

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

    private readonly IdsCollection $ids;

    protected function setUp(): void
    {
        Feature::skipTestIfInActive('ES_MULTILINGUAL_INDEX', $this);

        $this->ids = new IdsCollection();
    }

    public function testMapping(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(static::exactly(2))->method('fetchFirstColumn')->willReturn([
            'lang_en', 'lang_de',
        ]);

        $definition = new EsProductDefinition(
            $this->createMock(ProductDefinition::class),
            $connection,
            [],
            new EventDispatcher(),
            $this->createMock(AbstractProductSearchQueryBuilder::class)
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
                        'id' => [
                            'type' => 'keyword',
                            'normalizer' => 'sw_lowercase_normalizer',
                        ],
                        'name' => self::TRANSLATABLE_SEARCHABLE_MAPPING,
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
                        'name' => self::TRANSLATABLE_SEARCHABLE_MAPPING,
                        'groupId' => [
                            'type' => 'keyword',
                            'normalizer' => 'sw_lowercase_normalizer',
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
                'states' => [
                    'type' => 'keyword',
                    'normalizer' => 'sw_lowercase_normalizer',
                ],
                'manufacturerId' => [
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
        static::assertEquals($expectedMapping, $definition->getMapping(Context::createDefaultContext()));
    }

    public function testMappingCustomFields(): void
    {
        $connection = $this->createMock(Connection::class);

        $connection->expects(static::once())->method('fetchFirstColumn')->willReturn([
            'lang_en', 'lang_de',
        ]);

        $definition = new EsProductDefinition(
            $this->createMock(ProductDefinition::class),
            $connection,
            [
                'test1' => 'text',
                'test2' => 'unknown',
            ],
            new EventDispatcher(),
            $this->createMock(AbstractProductSearchQueryBuilder::class)
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
                'type' => 'text',
            ],
            $customFields['properties']['lang_en']['properties']['test1']
        );
        static::assertSame(
            [
                'type' => 'text',
            ],
            $customFields['properties']['lang_de']['properties']['test1']
        );

        static::assertArrayHasKey('test2', $customFields['properties']['lang_en']['properties']);
        static::assertArrayHasKey('test2', $customFields['properties']['lang_de']['properties']);
        static::assertSame(
            [
                'type' => 'keyword',
            ],
            $customFields['properties']['lang_en']['properties']['test2']
        );
        static::assertSame(
            [
                'type' => 'keyword',
            ],
            $customFields['properties']['lang_de']['properties']['test2']
        );
    }

    public function testGetDefinition(): void
    {
        $productDefinition = $this->createMock(ProductDefinition::class);
        $definition = new EsProductDefinition(
            $productDefinition,
            $this->createMock(Connection::class),
            [],
            new EventDispatcher(),
            $this->createMock(AbstractProductSearchQueryBuilder::class)
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

        $definition = new EsProductDefinition(
            $this->createMock(ProductDefinition::class),
            $this->createMock(Connection::class),
            [],
            new EventDispatcher(),
            $searchQueryBuilder
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
        $connection = $this->getConnection();

        $definition = new EsProductDefinition(
            $this->createMock(ProductDefinition::class),
            $connection,
            [],
            new EventDispatcher(),
            $this->createMock(AbstractProductSearchQueryBuilder::class)
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
                    '_count' => 1,
                    'groupId' => 'a73b9355da654243b92ce16c63e9b6cd',
                    'name' => [
                        Defaults::LANGUAGE_SYSTEM => 'Property A',
                    ],
                ],
                [
                    'id' => 'e4a08f9dd88f4a228240de7107e4ae4b',
                    '_count' => 1,
                    'groupId' => 'a73b9355da654243b92ce16c63e9b6cd',
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

        $definition = new EsProductDefinition(
            $this->createMock(ProductDefinition::class),
            $connection,
            ['bool' => CustomFieldTypes::BOOL, 'int' => CustomFieldTypes::INT],
            new EventDispatcher(),
            $this->createMock(AbstractProductSearchQueryBuilder::class)
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

    public function getConnection(): Connection
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
                [
                    '809c1844f4734243b6aa04aba860cd45' => [
                        'id' => '809c1844f4734243b6aa04aba860cd45',
                        'groupId' => 'a73b9355da654243b92ce16c63e9b6cd',
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
}
