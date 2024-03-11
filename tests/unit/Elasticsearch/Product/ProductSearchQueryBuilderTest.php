<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Product;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationDefinition;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductCategory\ProductCategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturerTranslation\ProductManufacturerTranslationDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductTag\ProductTagDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Filter\AbstractTokenFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Filter\TokenFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Tokenizer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\CustomField\CustomFieldService;
use Shopware\Core\System\Tag\TagDefinition;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Elasticsearch\Product\ProductSearchQueryBuilder;
use Shopware\Elasticsearch\Product\SearchConfigLoader;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(ProductSearchQueryBuilder::class)]
class ProductSearchQueryBuilderTest extends TestCase
{
    private const SECOND_LANGUAGE_ID = '2fbb5fe2e29a4d70aa5854ce7ce3e20c';

    /**
     * @param array{array{and_logic: string, field: string, tokenize: int, ranking: int}} $config
     * @param array{string: mixed} $expected
     */
    #[DataProvider('buildSingleLanguageProvider')]
    public function testBuildSingleLanguage(array $config, string $term, array $expected): void
    {
        $builder = $this->getBuilder($config);

        $criteria = new Criteria();
        $criteria->setTerm($term);

        $parsed = $builder->build($criteria, Context::createDefaultContext());

        $expected = [
            'bool' => $expected,
        ];

        static::assertSame($expected, $parsed->toArray());
    }

    /**
     * @param array{array{and_logic: string, field: string, tokenize: int, ranking: int}} $config
     * @param array{string: mixed} $expected
     */
    #[DataProvider('buildMultipleLanguageProvider')]
    public function testBuildMultipleLanguages(array $config, string $term, array $expected): void
    {
        $builder = $this->getBuilder($config);

        $criteria = new Criteria();
        $criteria->setTerm($term);

        $context = new Context(
            new SystemSource(),
            [],
            Defaults::CURRENCY,
            [Defaults::LANGUAGE_SYSTEM, self::SECOND_LANGUAGE_ID],
        );

        $parsed = $builder->build($criteria, $context);

        $expected = [
            'bool' => $expected,
        ];

        static::assertEquals($expected, $parsed->toArray());
    }

    /**
     * @return iterable<array-key, array{config: array{array{and_logic: string, field: string, tokenize: int, ranking: int}}, term: string, expected: array<string, mixed>}>
     */
    public static function buildSingleLanguageProvider(): iterable
    {
        $prefix = 'customFields.' . Defaults::LANGUAGE_SYSTEM . '.';

        yield 'Test tokenized fields' => [
            'config' => [
                self::config(field: 'name', ranking: 1000, tokenize: true, and: false),
                self::config(field: 'tags.name', ranking: 500, tokenize: true, and: false),
            ],
            'term' => 'foo',
            'expected' => [
                'should' => [
                    [
                        'bool' => [
                            'should' => [
                                self::match('name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo', 5000),
                                self::matchPhrasePrefix('name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo', 1000),
                                self::prefix('name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo', 1000),
                                self::match('name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo', 3000, 'auto'),
                                self::match('name.' . Defaults::LANGUAGE_SYSTEM . '.ngram', 'foo', 1000, null),
                                self::nested('tags', self::match('tags.name.search', 'foo', 2500)),
                                self::nested('tags', self::matchPhrasePrefix('tags.name.search', 'foo', 500)),
                                self::nested('tags', self::prefix('tags.name.search', 'foo', 500)),
                                self::nested('tags', self::match('tags.name.search', 'foo', 1500, 'auto')),
                                self::nested('tags', self::match('tags.name.ngram', 'foo', 500, null)),
                            ],
                        ],
                    ],
                ],
            ],
        ];

        yield 'Test multiple fields with terms' => [
            'config' => [
                self::config(field: 'name', ranking: 1000),
                self::config(field: 'ean', ranking: 2000),
                self::config(field: 'restockTime', ranking: 1500),
                self::config(field: 'tags.name', ranking: 500),
            ],
            'term' => 'foo 2023',
            'expected' => [
                'must' => [
                    [
                        'bool' => [
                            'should' => [
                                self::match('name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo', 5000),
                                self::matchPhrasePrefix('name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo', 1000),
                                self::match('ean.search', 'foo', 10000),
                                self::matchPhrasePrefix('ean.search', 'foo', 2000),
                                self::nested('tags', self::match('tags.name.search', 'foo', 2500)),
                                self::nested('tags', self::matchPhrasePrefix('tags.name.search', 'foo', 500)),
                            ],
                        ],
                    ],
                    [
                        'bool' => [
                            'should' => [
                                self::match('name.' . Defaults::LANGUAGE_SYSTEM . '.search', '2023', 5000),
                                self::matchPhrasePrefix('name.' . Defaults::LANGUAGE_SYSTEM . '.search', '2023', 1000),
                                self::match('ean.search', '2023', 10000),
                                self::matchPhrasePrefix('ean.search', '2023', 2000),
                                self::term('restockTime', 2023, 7500),
                                self::nested('tags', self::match('tags.name.search', '2023', 2500)),
                                self::nested('tags', self::matchPhrasePrefix('tags.name.search', '2023', 500)),
                            ],
                        ],
                    ],
                ],
            ],
        ];

        yield 'Test multiple custom fields with terms' => [
            'config' => [
                self::config(field: 'customFields.evolvesText', ranking: 500),
                self::config(field: 'customFields.evolvesInt', ranking: 400),
                self::config(field: 'customFields.evolvesFloat', ranking: 500),
                self::config(field: 'categories.childCount', ranking: 500),
            ],
            'term' => 'foo 2023',
            'expected' => [
                'must' => [
                    [
                        'bool' => [
                            'should' => [
                                self::match($prefix . 'evolvesText', 'foo', 2500),
                                self::matchPhrasePrefix($prefix . 'evolvesText', 'foo', 500),
                            ],
                        ],
                    ],
                    [
                        'bool' => [
                            'should' => [
                                self::match($prefix . 'evolvesText', '2023', 2500),
                                self::matchPhrasePrefix($prefix . 'evolvesText', '2023', 500),
                                self::term($prefix . 'evolvesInt', 2023, 2000),
                                self::term($prefix . 'evolvesFloat', 2023.0, 2500),
                                self::nested('categories', self::term('categories.childCount', 2023, 2500)),
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return iterable<array-key, array{config: array{array{and_logic: string, field: string, tokenize: int, ranking: int}}, term: string, expected: array<string, mixed>}>
     */
    public static function buildMultipleLanguageProvider(): iterable
    {
        $prefixCfLang1 = 'customFields.' . Defaults::LANGUAGE_SYSTEM . '.';
        $prefixCfLang2 = 'customFields.' . self::SECOND_LANGUAGE_ID . '.';

        yield 'Test tokenized fields' => [
            'config' => [
                self::config(field: 'name', ranking: 1000, tokenize: true, and: false),
                self::config(field: 'tags.name', ranking: 500, tokenize: true, and: false),
                self::config(field: 'categories.name', ranking: 200, tokenize: true, and: false),
            ],
            'term' => 'foo',
            'expected' => [
                'should' => [
                    [
                        'bool' => [
                            'should' => [
                                self::multiMatch(fields: [
                                    'name.' . Defaults::LANGUAGE_SYSTEM . '.search',
                                    'name.' . self::SECOND_LANGUAGE_ID . '.search',
                                ], query: 'foo', lenient: true, boost: 5000, fuzziness: 0),
                                self::multiMatch(fields: [
                                    'name.' . Defaults::LANGUAGE_SYSTEM . '.search',
                                    'name.' . self::SECOND_LANGUAGE_ID . '.search',
                                ], query: 'foo', boost: 1000, slop: 5, type: 'phrase_prefix'),
                                self::multiMatch(fields: [
                                    'name.' . Defaults::LANGUAGE_SYSTEM . '.search',
                                    'name.' . self::SECOND_LANGUAGE_ID . '.search',
                                ], query: 'foo', boost: 3000, fuzziness: 'auto'),
                                self::multiMatch(fields: [
                                    'name.' . Defaults::LANGUAGE_SYSTEM . '.ngram',
                                    'name.' . self::SECOND_LANGUAGE_ID . '.ngram',
                                ], query: 'foo', boost: 1000, type: 'phrase'),
                                self::nested('tags', self::match('tags.name.search', 'foo', 2500)),
                                self::nested('tags', self::matchPhrasePrefix('tags.name.search', 'foo', 500)),
                                self::nested('tags', self::prefix('tags.name.search', 'foo', 500)),
                                self::nested('tags', self::match('tags.name.search', 'foo', 1500, 'auto')),
                                self::nested('tags', self::match('tags.name.ngram', 'foo', 500, null)),
                                self::nested('categories', self::multiMatch(fields: [
                                    'categories.name.' . Defaults::LANGUAGE_SYSTEM . '.search',
                                    'categories.name.' . self::SECOND_LANGUAGE_ID . '.search',
                                ], query: 'foo', lenient: true, boost: 1000, fuzziness: 0)),
                                self::nested('categories', self::multiMatch(fields: [
                                    'categories.name.' . Defaults::LANGUAGE_SYSTEM . '.search',
                                    'categories.name.' . self::SECOND_LANGUAGE_ID . '.search',
                                ], query: 'foo', boost: 200, slop: 5, type: 'phrase_prefix')),
                                self::nested('categories', self::multiMatch(fields: [
                                    'categories.name.' . Defaults::LANGUAGE_SYSTEM . '.search',
                                    'categories.name.' . self::SECOND_LANGUAGE_ID . '.search',
                                ], query: 'foo', boost: 600, fuzziness: 'auto')),
                                self::nested('categories', self::multiMatch(fields: [
                                    'categories.name.' . Defaults::LANGUAGE_SYSTEM . '.ngram',
                                    'categories.name.' . self::SECOND_LANGUAGE_ID . '.ngram',
                                ], query: 'foo', boost: 200, type: 'phrase')),
                            ],
                        ],
                    ],
                ],
            ],
        ];

        yield 'Test multiple fields with terms' => [
            'config' => [
                self::config(field: 'name', ranking: 1000),
                self::config(field: 'ean', ranking: 2000),
                self::config(field: 'restockTime', ranking: 1500),
                self::config(field: 'tags.name', ranking: 500),
            ],
            'term' => 'foo 2023',
            'expected' => [
                'must' => [
                    [
                        'bool' => [
                            'should' => [
                                self::multiMatch(fields: [
                                    'name.' . Defaults::LANGUAGE_SYSTEM . '.search',
                                    'name.' . self::SECOND_LANGUAGE_ID . '.search',
                                ], query: 'foo', lenient: true, boost: 5000, fuzziness: 0),
                                self::multiMatch(fields: [
                                    'name.' . Defaults::LANGUAGE_SYSTEM . '.search',
                                    'name.' . self::SECOND_LANGUAGE_ID . '.search',
                                ], query: 'foo', boost: 1000, slop: 5, type: 'phrase_prefix'),
                                self::match('ean.search', 'foo', 10000),
                                self::matchPhrasePrefix('ean.search', 'foo', 2000),
                                self::nested('tags', self::match('tags.name.search', 'foo', 2500)),
                                self::nested('tags', self::matchPhrasePrefix('tags.name.search', 'foo', 500)),
                            ],
                        ],
                    ],
                    [
                        'bool' => [
                            'should' => [
                                self::multiMatch(fields: [
                                    'name.' . Defaults::LANGUAGE_SYSTEM . '.search',
                                    'name.' . self::SECOND_LANGUAGE_ID . '.search',
                                ], query: '2023', lenient: true, boost: 5000, fuzziness: 0),
                                self::multiMatch(fields: [
                                    'name.' . Defaults::LANGUAGE_SYSTEM . '.search',
                                    'name.' . self::SECOND_LANGUAGE_ID . '.search',
                                ], query: '2023', boost: 1000, slop: 5, type: 'phrase_prefix'),
                                self::match('ean.search', '2023', 10000),
                                self::matchPhrasePrefix('ean.search', '2023', 2000),
                                self::term('restockTime', 2023, 7500),
                                self::nested('tags', self::match('tags.name.search', '2023', 2500)),
                                self::nested('tags', self::matchPhrasePrefix('tags.name.search', '2023', 500)),
                            ],
                        ],
                    ],
                ],
            ],
        ];

        yield 'Test multiple custom fields with terms' => [
            'config' => [
                self::config(field: 'customFields.evolvesText', ranking: 500),
                self::config(field: 'customFields.evolvesInt', ranking: 400),
                self::config(field: 'customFields.evolvesFloat', ranking: 500),
                self::config(field: 'categories.childCount', ranking: 500),
            ],
            'term' => 'foo 2023',
            'expected' => [
                'must' => [
                    [
                        'bool' => [
                            'should' => [
                                self::multiMatch(fields: [
                                    $prefixCfLang1 . 'evolvesText',
                                    $prefixCfLang2 . 'evolvesText',
                                ], query: 'foo', lenient: true, boost: 2500, fuzziness: 0),
                                self::multiMatch(fields: [
                                    $prefixCfLang1 . 'evolvesText',
                                    $prefixCfLang2 . 'evolvesText',
                                ], query: 'foo', boost: 500, slop: 5, type: 'phrase_prefix'),
                                self::multiMatch(fields: [
                                    $prefixCfLang1 . 'evolvesText',
                                    $prefixCfLang2 . 'evolvesText',
                                ], query: 'foo', boost: 1500, lenient: true, fuzziness: 'auto'),
                            ],
                        ],
                    ],
                    [
                        'bool' => [
                            'should' => [
                                self::multiMatch(fields: [
                                    $prefixCfLang1 . 'evolvesText',
                                    $prefixCfLang2 . 'evolvesText',
                                ], query: '2023', lenient: true, boost: 2500, fuzziness: 0),
                                self::multiMatch(fields: [
                                    $prefixCfLang1 . 'evolvesText',
                                    $prefixCfLang2 . 'evolvesText',
                                ], query: '2023', boost: 500, slop: 5, type: 'phrase_prefix'),
                                self::multiMatch(fields: [
                                    $prefixCfLang1 . 'evolvesText',
                                    $prefixCfLang2 . 'evolvesText',
                                ], query: '2023', boost: 1500, lenient: true, fuzziness: 'auto'),

                                self::disMax(queries: [
                                    self::term($prefixCfLang1 . 'evolvesInt', 2023, 2000),
                                    self::term($prefixCfLang2 . 'evolvesInt', 2023, 2000),
                                ]),
                                self::disMax(queries: [
                                    self::term($prefixCfLang1 . 'evolvesFloat', 2023.0, 2500),
                                    self::term($prefixCfLang2 . 'evolvesFloat', 2023.0, 2500),
                                ]),
                                self::nested('categories', self::term('categories.childCount', 2023, 2500)),
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function testDecoration(): void
    {
        $builder = new ProductSearchQueryBuilder(
            new EntityDefinitionQueryHelper(),
            $this->getDefinition(),
            $this->createMock(TokenFilter::class),
            new Tokenizer(2),
            $this->createMock(CustomFieldService::class),
            $this->createMock(SearchConfigLoader::class)
        );

        static::expectException(DecorationPatternException::class);
        $builder->getDecorated();
    }

    private function getDefinition(): EntityDefinition
    {
        $instanceRegistry = new StaticDefinitionInstanceRegistry(
            [
                ProductDefinition::class,
                ProductTagDefinition::class,
                TagDefinition::class,
                ProductTranslationDefinition::class,
                ProductManufacturerDefinition::class,
                ProductManufacturerTranslationDefinition::class,
                ProductCategoryDefinition::class,
                CategoryDefinition::class,
                CategoryTranslationDefinition::class,
            ],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
        );

        return $instanceRegistry->getByEntityName('product');
    }

    /**
     * @param array{array{and_logic: string, field: string, tokenize: int, ranking: int}} $config
     */
    private function getBuilder(array $config): ProductSearchQueryBuilder
    {
        $configLoader = $this->createMock(SearchConfigLoader::class);
        $configLoader->method('load')->willReturn($config);

        $tokenFilter = $this->createMock(AbstractTokenFilter::class);
        $tokenFilter->method('filter')->willReturnArgument(0);

        return new ProductSearchQueryBuilder(
            new EntityDefinitionQueryHelper(),
            $this->getDefinition(),
            $tokenFilter,
            new Tokenizer(2),
            new CustomFieldServiceStub([
                'evolvesInt' => new IntField('evolvesInt', 'evolvesInt'),
                'evolvesFloat' => new FloatField('evolvesFloat', 'evolvesFloat'),
                'evolvesText' => new StringField('evolvesText', 'evolvesText'),
            ]),
            $configLoader
        );
    }

    /**
     * @return array{and_logic: string, field: string, tokenize: int, ranking: int}
     */
    private static function config(string $field, int $ranking, bool $tokenize = false, bool $and = true): array
    {
        return [
            'and_logic' => $and ? '1' : '0',
            'field' => $field,
            'tokenize' => $tokenize ? 1 : 0,
            'ranking' => $ranking,
        ];
    }

    /**
     * @return array{term: array<string, array{value: string|int|float, boost: int, case_insensitive: bool}>}
     */
    private static function term(string $field, string|int|float $query, int $boost): array
    {
        return [
            'term' => [
                $field => [
                    'boost' => $boost,
                    'case_insensitive' => true,
                    'value' => $query,
                ],
            ],
        ];
    }

    /**
     * @param array<mixed> $query
     *
     * @return array{nested: array{path: string, query: array<mixed>}}
     */
    private static function nested(string $root, array $query): array
    {
        return [
            'nested' => [
                'path' => $root,
                'query' => $query,
            ],
        ];
    }

    /**
     * @return array{match: array<string, array{query: string|int|float, boost: int, fuzziness?: int|string|null}>}
     */
    private static function match(string $field, string|int|float $query, int $boost, int|string|null $fuzziness = 0): array
    {
        $payload = [
            'query' => $query,
            'boost' => $boost,
        ];

        if ($fuzziness !== null) {
            $payload['fuzziness'] = $fuzziness;
        }

        return [
            'match' => [
                $field => $payload,
            ],
        ];
    }

    /**
     * @param array<mixed> $queries
     *
     * @return array{dis_max: array{queries: array<mixed>}}
     */
    private static function disMax(array $queries): array
    {
        return [
            'dis_max' => [
                'queries' => $queries,
            ],
        ];
    }

    /**
     * @param array<string> $fields
     *
     * @return array{multi_match: array{query: string|int|float, boost: int, fuzziness?: int|string|null}}
     */
    private static function multiMatch(
        array $fields,
        string|int|float $query,
        int $boost,
        string $type = 'best_fields',
        int|string|null $fuzziness = null,
        ?bool $lenient = null,
        ?int $slop = null
    ): array {
        $payload = [
            'query' => $query,
            'fields' => $fields,
            'type' => $type,
            'boost' => $boost,
        ];

        if ($slop !== null) {
            $payload['slop'] = $slop;
        }

        if ($fuzziness !== null) {
            $payload['fuzziness'] = $fuzziness;
        }

        if ($lenient !== null) {
            $payload['lenient'] = $lenient;
        }

        return [
            'multi_match' => $payload,
        ];
    }

    /**
     * @return array{prefix: array<string, array{value: string|int|float, boost: int}>}
     */
    private static function prefix(string $field, string|int|float $query, int $boost): array
    {
        return [
            'prefix' => [
                $field => [
                    'value' => $query,
                    'boost' => $boost,
                ],
            ],
        ];
    }

    /**
     * @return array{match_phrase_prefix: array<string, array{query: string|int|float, boost: int, slop: int}>}
     */
    private static function matchPhrasePrefix(string $field, string|int|float $query, int $boost, int $slop = 5): array
    {
        return [
            'match_phrase_prefix' => [
                $field => [
                    'query' => $query,
                    'boost' => $boost,
                    'slop' => $slop,
                ],
            ],
        ];
    }
}

/**
 * @internal
 */
class CustomFieldServiceStub extends CustomFieldService
{
    /**
     * @internal
     *
     * @param array<string, Field> $config
     */
    public function __construct(private readonly array $config)
    {
    }

    public function getCustomField(string $attributeName): ?Field
    {
        return $this->config[$attributeName] ?? null;
    }
}
