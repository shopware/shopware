<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Product;

use Doctrine\DBAL\Connection;
use OpenSearchDSL\Query\Compound\BoolQuery;
use OpenSearchDSL\Query\FullText\MatchQuery;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\CustomField\CustomFieldTypes;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Elasticsearch\Framework\Indexing\EntityMapper;
use Shopware\Elasticsearch\Product\AbstractProductSearchQueryBuilder;
use Shopware\Elasticsearch\Product\ElasticsearchProductDefinition;
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

    public function testMapping(): void
    {
        $definition = new ElasticsearchProductDefinition(
            $this->createMock(ProductDefinition::class),
            $this->createMock(EntityMapper::class),
            $this->createMock(Connection::class),
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
                ],
                'createdAt' => [
                    'type' => 'date',
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
                        'id' => [
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
                    ],
                ],
                'customSearchKeywords' => self::SEARCHABLE_MAPPING,
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
        $definition = new ElasticsearchProductDefinition(
            $this->createMock(ProductDefinition::class),
            $this->createMock(EntityMapper::class),
            $this->createMock(Connection::class),
            [
                'test1' => 'text',
                'test2' => 'unknown',
            ],
            new EventDispatcher(),
            $this->createMock(AbstractProductSearchQueryBuilder::class)
        );

        $mapping = $definition->getMapping(Context::createDefaultContext());

        $customFields = $mapping['properties']['customFields'];

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
            $this->createMock(EntityMapper::class),
            $this->createMock(Connection::class),
            [],
            new EventDispatcher(),
            $this->createMock(AbstractProductSearchQueryBuilder::class)
        );

        static::assertSame($productDefinition, $definition->getEntityDefinition());
    }

    /**
     * @DisabledFeatures(features={"v6.5.0.0"})
     */
    public function testExtendDocument(): void
    {
        $definition = new ElasticsearchProductDefinition(
            $this->createMock(ProductDefinition::class),
            $this->createMock(EntityMapper::class),
            $this->createMock(Connection::class),
            [],
            new EventDispatcher(),
            $this->createMock(AbstractProductSearchQueryBuilder::class)
        );

        static::assertEquals(['foo' => 'bar'], $definition->extendDocuments(['foo' => 'bar'], Context::createDefaultContext()));
    }

    /**
     * @DisabledFeatures(features={"FEATURE_NEXT_22900"})
     */
    public function testBuildTermQuery(): void
    {
        $definition = new ElasticsearchProductDefinition(
            $this->createMock(ProductDefinition::class),
            $this->createMock(EntityMapper::class),
            $this->createMock(Connection::class),
            [],
            new EventDispatcher(),
            $this->createMock(AbstractProductSearchQueryBuilder::class)
        );

        $criteria = new Criteria();
        $criteria->setTerm('test');
        $query = $definition->buildTermQuery(Context::createDefaultContext(), $criteria);

        $queries = $query->toArray();

        $expected = [
            'bool' => [
                'should' => [[
                    'match' => [
                        'fullTextBoosted' => [
                            'query' => 'test',
                            'boost' => 10,
                        ],
                    ],
                ],
                    [
                        'match' => [
                            'fullText' => [
                                'query' => 'test',
                                'boost' => 5,
                            ],
                        ],
                    ],
                    [
                        'match' => [
                            'fullText' => [
                                'query' => 'test',
                                'fuzziness' => 'auto',
                                'boost' => 3,
                            ],
                        ],
                    ],
                    [
                        'match_phrase_prefix' => [
                            'fullText' => [
                                'query' => 'test',
                                'boost' => 1,
                                'slop' => 5,
                            ],
                        ],
                    ],
                    [
                        'wildcard' => [
                            'fullText' => [
                                'value' => '*test*',
                            ],
                        ],
                    ],
                    [
                        'match' => [
                            'fullText.ngram' => [
                                'query' => 'test',
                            ],
                        ],
                    ],
                    [
                        'match' => [
                            'description' => [
                                'query' => 'test',
                            ],
                        ],
                    ],
                ],
                'minimum_should_match' => 1,
            ],
        ];

        static::assertSame($expected, $queries);
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
            $this->createMock(EntityMapper::class),
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

        $definition = new ElasticsearchProductDefinition(
            $this->createMock(ProductDefinition::class),
            $this->createMock(EntityMapper::class),
            $connection,
            [],
            new EventDispatcher(),
            $this->createMock(AbstractProductSearchQueryBuilder::class)
        );

        $documents = $definition->fetch(['1'], Context::createDefaultContext());

        static::assertArrayHasKey('1', $documents);

        $document = $documents['1'];

        static::assertSame(1, $document['id']);
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
                    '_count' => 1,
                ],
                [
                    'id' => 'e4a08f9dd88f4a228240de7107e4ae4b',
                    'name' => 'Property B',
                    'groupId' => 'a73b9355da654243b92ce16c63e9b6cd',
                    '_count' => 1,
                ],
            ],
            $document['properties']
        );
    }

    /**
     * @DisabledFeatures(features={"v6.5.0.0"})
     */
    public function testFetchFormatsCustomFields(): void
    {
        $connection = $this->getConnection();

        $definition = new ElasticsearchProductDefinition(
            $this->createMock(ProductDefinition::class),
            $this->createMock(EntityMapper::class),
            $connection,
            ['bool' => CustomFieldTypes::BOOL, 'int' => CustomFieldTypes::INT],
            new EventDispatcher(),
            $this->createMock(AbstractProductSearchQueryBuilder::class)
        );

        $documents = $definition->fetch(['1'], Context::createDefaultContext());

        static::assertArrayHasKey('1', $documents);
        static::assertArrayHasKey('customFields', $documents['1']);
        static::assertArrayHasKey('bool', $documents['1']['customFields']);
        static::assertIsBool($documents['1']['customFields']['bool']);
        static::assertArrayHasKey('int', $documents['1']['customFields']);
        static::assertIsFloat($documents['1']['customFields']['int']);
        static::assertArrayHasKey('unknown', $documents['1']['customFields']);
        static::assertSame('foo', $documents['1']['customFields']['unknown']);
    }

    public function testFetchFormatsCustomFieldsAndRemovesNotMappedFields(): void
    {
        $connection = $this->getConnection();

        $definition = new ElasticsearchProductDefinition(
            $this->createMock(ProductDefinition::class),
            $this->createMock(EntityMapper::class),
            $connection,
            ['bool' => CustomFieldTypes::BOOL, 'int' => CustomFieldTypes::INT],
            new EventDispatcher(),
            $this->createMock(AbstractProductSearchQueryBuilder::class)
        );

        $documents = $definition->fetch(['1'], Context::createDefaultContext());

        static::assertArrayHasKey('1', $documents);
        static::assertArrayHasKey('customFields', $documents['1']);
        static::assertArrayHasKey('bool', $documents['1']['customFields']);
        static::assertIsBool($documents['1']['customFields']['bool']);
        static::assertArrayHasKey('int', $documents['1']['customFields']);
        static::assertIsFloat($documents['1']['customFields']['int']);
        static::assertArrayNotHasKey('unknown', $documents['1']['customFields']);
    }

    public function getConnection(): Connection
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchAllAssociative')
            ->willReturnOnConsecutiveCalls(
                [
                    [
                        'id' => 1,
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
