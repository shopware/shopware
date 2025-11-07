<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch;

use OpenSearchDSL\Query\Compound\BoolQuery;
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
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\SearchConfigLoader;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Filter\TokenFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Tokenizer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\CustomField\CustomFieldService;
use Shopware\Core\System\Tag\TagDefinition;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Core\Test\Stub\Framework\Adapter\Storage\ArrayKeyValueStorage;
use Shopware\Elasticsearch\Product\ElasticsearchOptimizeSwitch;
use Shopware\Elasticsearch\Product\ProductSearchQueryBuilder;
use Shopware\Elasticsearch\Product\SearchFieldConfig;
use Shopware\Elasticsearch\TokenQueryBuilder;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(TokenQueryBuilder::class)]
#[Package('inventory')]
class TokenQueryBuilderTest extends TestCase
{
    private const SECOND_LANGUAGE_ID = '2fbb5fe2e29a4d70aa5854ce7ce3e20c';

    private TokenQueryBuilder $tokenQueryBuilder;

    protected function setUp(): void
    {
        $storage = new ArrayKeyValueStorage([ElasticsearchOptimizeSwitch::FLAG => true]);

        $this->tokenQueryBuilder = new TokenQueryBuilder(
            $this->getRegistry(),
            new CustomFieldServiceMock([
                'evolvesInt' => new IntField('evolvesInt', 'evolvesInt'),
                'evolvesFloat' => new FloatField('evolvesFloat', 'evolvesFloat'),
                'evolvesText' => new StringField('evolvesText', 'evolvesText'),
            ]),
            $storage
        );
    }

    public function testBuildWithInvalidField(): void
    {
        $query = $this->tokenQueryBuilder->build('product', 'foo', [
            self::config(field: 'invalid', ranking: 1500),
        ], Context::createDefaultContext());
        static::assertNull($query);
    }

    public function testBuildWithoutFields(): void
    {
        $query = $this->tokenQueryBuilder->build('product', 'foo', [], Context::createDefaultContext());
        static::assertNull($query);
    }

    public function testBuildWithExplainMode(): void
    {
        $config = [
            self::config(field: 'name', ranking: 1000, tokenize: true, and: false),
            self::config(field: 'tags.name', ranking: 500, tokenize: true, and: false),
        ];

        $term = 'foo';

        $context = Context::createDefaultContext();
        $context->assign([
            'languageIdChain' => [Defaults::LANGUAGE_SYSTEM],
        ]);

        $context->addState(Context::ELASTICSEARCH_EXPLAIN_MODE);

        $query = $this->tokenQueryBuilder->build('product', $term, $config, $context);

        static::assertNotNull($query);

        $expectedFuzziness = 'AUTO:3,8';
        $expectedMaxExpansions = 5;

        $nameQuery = self::disMax([
            self::term('name.' . Defaults::LANGUAGE_SYSTEM, 'foo', 1),
            self::match('name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo', 0.8, $expectedFuzziness, 'or', $expectedMaxExpansions),
            self::prefix('name.' . Defaults::LANGUAGE_SYSTEM, 'foo', 0.4),
        ], 1000);

        $nameQuery['dis_max']['_name'] = json_encode([
            'field' => 'name',
            'term' => 'foo',
            'ranking' => 1000,
        ]);

        $tagQuery = self::disMax([
            self::term('tags.name', 'foo', 1),
            self::match('tags.name.search', 'foo', 0.8, $expectedFuzziness, 'or', $expectedMaxExpansions),
            self::prefix('tags.name', 'foo', 0.4),
        ], 500);

        $expected = self::bool([
            $nameQuery,
            self::nested(root: 'tags', query: $tagQuery, explainPayload: [
                'inner_hits' => [
                    '_source' => false,
                    'explain' => true,
                    'name' => json_encode([
                        'field' => 'tags.name',
                        'term' => 'foo',
                        'ranking' => 500,
                    ]),
                ],
                '_name' => json_encode([
                    'field' => 'tags.name',
                    'term' => 'foo',
                    'ranking' => 500,
                ]),
            ]),
        ]);

        static::assertSame($expected, $query->toArray());
    }

    public function testBuildWithSynonyms(): void
    {
        $config = [
            self::config(field: 'name', ranking: 1000, tokenize: true, and: false, prefixMatch: false),
            self::config(field: 'tags.name', ranking: 500, tokenize: true, and: false),
        ];

        $term = 'foo';

        $context = Context::createDefaultContext();
        $context->assign([
            'languageIdChain' => [Defaults::LANGUAGE_SYSTEM],
        ]);

        $query = $this->tokenQueryBuilder->build('product', $term, $config, $context);

        static::assertNotNull($query);

        $expectedFuzziness = 'AUTO:3,8';
        $expectedMaxExpansions = 5;

        $expected = self::bool([
            self::disMax([
                self::term('name.' . Defaults::LANGUAGE_SYSTEM, 'foo', 1),
                self::match('name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo', 0.8, $expectedFuzziness, 'or', $expectedMaxExpansions),
                self::prefix('name.' . Defaults::LANGUAGE_SYSTEM, 'foo', 0.4),
            ], 1000),
            self::nested('tags', self::disMax([
                self::term('tags.name', 'foo', 1),
                self::match('tags.name.search', 'foo', 0.8, $expectedFuzziness, 'or', $expectedMaxExpansions),
                self::prefix('tags.name', 'foo', 0.4),
            ], 500)),
        ]);

        static::assertSame($expected, $query->toArray());
    }

    /**
     * @param SearchFieldConfig[] $config
     * @param array{string: mixed} $expected
     */
    #[DataProvider('buildSingleLanguageProvider')]
    public function testBuildSingleLanguage(array $config, string $term, array $expected): void
    {
        $context = Context::createDefaultContext();
        $context->assign([
            'languageIdChain' => [Defaults::LANGUAGE_SYSTEM],
        ]);

        $query = $this->tokenQueryBuilder->build('product', $term, $config, $context);

        static::assertNotNull($query);
        static::assertSame($expected, $query->toArray());
    }

    /**
     * @param SearchFieldConfig[] $config
     * @param array{string: mixed} $expected
     */
    #[DataProvider('buildMultipleLanguageProvider')]
    public function testBuildMultipleLanguages(array $config, string $term, array $expected): void
    {
        $context = Context::createDefaultContext();
        $context->assign([
            'languageIdChain' => [Defaults::LANGUAGE_SYSTEM, self::SECOND_LANGUAGE_ID],
        ]);

        $query = $this->tokenQueryBuilder->build('product', $term, $config, $context);

        static::assertNotNull($query);
        static::assertSame($expected, $query->toArray());
    }

    /**
     * @return iterable<array-key, array{config: SearchFieldConfig[], term: string, expected: array<string, mixed>}>
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
            'expected' => self::bool([
                self::disMax([
                    self::term('name.' . Defaults::LANGUAGE_SYSTEM, 'foo', 1),
                    self::match('name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo', 0.8, 'AUTO:3,8', 'or', 5),
                    self::prefix('name.' . Defaults::LANGUAGE_SYSTEM, 'foo', 0.4),
                ], 1000),
                self::nested('tags', self::disMax([
                    self::term('tags.name', 'foo', 1),
                    self::match('tags.name.search', 'foo', 0.8, 'AUTO:3,8', 'or', 5),
                    self::prefix('tags.name', 'foo', 0.4),
                ], 500)),
            ]),
        ];

        yield 'Test multiple fields' => [
            'config' => [
                self::config(field: 'name', ranking: 1000),
                self::config(field: 'ean', ranking: 2000),
                self::config(field: 'restockTime', ranking: 1500),
                self::config(field: 'tags.name', ranking: 500),
            ],
            'term' => 'foo 2023',
            'expected' => self::bool([
                self::disMax([
                    self::terms('name.' . Defaults::LANGUAGE_SYSTEM, ['foo', '2023'], 1),
                    self::match('name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo 2023', 0.8, 0, 'and', 10),
                    self::matchPhrasePrefix('name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo 2023', 0.6, 3, 10),
                ], 1000),
                self::disMax([
                    self::terms('ean', ['foo', '2023'], 1),
                    self::match('ean.search', 'foo 2023', 0.8, 0, 'and', 10),
                    self::matchPhrasePrefix('ean.search', 'foo 2023', 0.6, 3, 10),
                ], 2000),
                self::nested('tags', self::disMax([
                    self::terms('tags.name', ['foo', '2023'], 1),
                    self::match('tags.name.search', 'foo 2023', 0.8, 0, 'and', 10),
                    self::matchPhrasePrefix('tags.name.search', 'foo 2023', 0.6, 3, 10),
                ], 500)),
            ]),
        ];

        yield 'Test multiple custom fields with terms' => [
            'config' => [
                self::config(field: 'customFields.evolvesText', ranking: 500),
                self::config(field: 'customFields.evolvesInt', ranking: 400),
                self::config(field: 'customFields.evolvesFloat', ranking: 500),
                self::config(field: 'categories.childCount', ranking: 500),
            ],
            'term' => '2023',
            'expected' => self::bool([
                self::disMax([
                    self::term($prefix . 'evolvesText', '2023', 1),
                    self::match($prefix . 'evolvesText.search', '2023', 0.8, 0, 'and', 10),
                ], 500),
                self::term($prefix . 'evolvesInt', 2023, 400),
                self::term($prefix . 'evolvesFloat', 2023.0, 500),
                self::nested('categories', self::term('categories.childCount', 2023, 500)),
            ]),
        ];
    }

    /**
     * @return iterable<array-key, array{config: SearchFieldConfig[], term: string, expected: array<string, mixed>}>
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
            'expected' => self::bool([
                self::disMax([
                    self::term('name.' . Defaults::LANGUAGE_SYSTEM, 'foo', 1),
                    self::match('name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo', 0.8, 'AUTO:3,8', 'or', 5),
                    self::prefix('name.' . Defaults::LANGUAGE_SYSTEM, 'foo', 0.4),
                ], 1000),
                self::nested('tags', self::disMax([
                    self::term('tags.name', 'foo', 1),
                    self::match('tags.name.search', 'foo', 0.8, 'AUTO:3,8', 'or', 5),
                    self::prefix('tags.name', 'foo', 0.4),
                ], 500)),
                self::nested('categories', self::disMax([
                    self::disMax([
                        self::term('categories.name.' . Defaults::LANGUAGE_SYSTEM, 'foo', 1),
                        self::match('categories.name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo', 0.8, 'AUTO:3,8', 'or', 5),
                        self::prefix('categories.name.' . Defaults::LANGUAGE_SYSTEM, 'foo', 0.4),
                    ], 200),
                    self::disMax([
                        self::term('categories.name.' . self::SECOND_LANGUAGE_ID, 'foo', 1),
                        self::match('categories.name.' . self::SECOND_LANGUAGE_ID . '.search', 'foo', 0.8, 'AUTO:3,8', 'or', 5),
                        self::prefix('categories.name.' . self::SECOND_LANGUAGE_ID, 'foo', 0.4),
                    ], 160),
                ])),
            ]),
        ];

        yield 'Test multiple fields with terms' => [
            'config' => [
                self::config(field: 'name', ranking: 1000),
                self::config(field: 'ean', ranking: 2000),
                self::config(field: 'restockTime', ranking: 1500),
                self::config(field: 'tags.name', ranking: 500),
            ],
            'term' => 'foo 2023',
            'expected' => self::bool([
                self::disMax([
                    self::terms('name.' . Defaults::LANGUAGE_SYSTEM, ['foo', '2023'], 1),
                    self::match('name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo 2023', 0.8, 0, 'and', 10),
                    self::matchPhrasePrefix('name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo 2023', 0.6, 3, 10),
                ], 1000),
                self::disMax([
                    self::terms('ean', ['foo', '2023'], 1),
                    self::match('ean.search', 'foo 2023', 0.8, 0, 'and', 10),
                    self::matchPhrasePrefix('ean.search', 'foo 2023', 0.6, 3, 10),
                ], 2000),
                self::nested('tags', self::disMax([
                    self::terms('tags.name', ['foo', '2023'], 1),
                    self::match('tags.name.search', 'foo 2023', 0.8, 0, 'and', 10),
                    self::matchPhrasePrefix('tags.name.search', 'foo 2023', 0.6, 3, 10),
                ], 500)),
            ]),
        ];

        yield 'Test multiple custom fields with numeric term' => [
            'config' => [
                self::config(field: 'customFields.evolvesText', ranking: 500),
                self::config(field: 'customFields.evolvesInt', ranking: 400),
                self::config(field: 'customFields.evolvesFloat', ranking: 500),
                self::config(field: 'categories.childCount', ranking: 500),
            ],
            'term' => '2023',
            'expected' => self::bool([
                self::disMax([
                    self::disMax([
                        self::term($prefixCfLang1 . 'evolvesText', '2023', 1),
                        self::match($prefixCfLang1 . 'evolvesText.search', '2023', 0.8, 0, 'and', 10),
                    ], 500),
                    self::disMax([
                        self::term($prefixCfLang2 . 'evolvesText', '2023', 1),
                        self::match($prefixCfLang2 . 'evolvesText.search', '2023', 0.8, 0, 'and', 10),
                    ], 400),
                ]),
                self::disMax([
                    self::term($prefixCfLang1 . 'evolvesInt', 2023, 400),
                    self::term($prefixCfLang2 . 'evolvesInt', 2023, 320),
                ]),
                self::disMax([
                    self::term($prefixCfLang1 . 'evolvesFloat', 2023.0, 500),
                    self::term($prefixCfLang2 . 'evolvesFloat', 2023.0, 400),
                ]),
                self::nested('categories', self::term('categories.childCount', 2023, 500)),
            ]),
        ];

        yield 'Test multiple custom fields with text term' => [
            'config' => [
                self::config(field: 'customFields.evolvesText', ranking: 500),
                self::config(field: 'customFields.evolvesInt', ranking: 400),
                self::config(field: 'customFields.evolvesFloat', ranking: 500),
                self::config(field: 'categories.childCount', ranking: 500),
            ],
            'term' => 'foo',
            'expected' => self::disMax([
                self::disMax([
                    self::term($prefixCfLang1 . 'evolvesText', 'foo', 1),
                    self::match($prefixCfLang1 . 'evolvesText.search', 'foo', 0.8, 'AUTO:3,8', 'and', 5),
                    self::prefix($prefixCfLang1 . 'evolvesText', 'foo', 0.4),
                ], 500),
                self::disMax([
                    self::term($prefixCfLang2 . 'evolvesText', 'foo', 1),
                    self::match($prefixCfLang2 . 'evolvesText.search', 'foo', 0.8, 'AUTO:3,8', 'and', 5),
                    self::prefix($prefixCfLang2 . 'evolvesText', 'foo', 0.4),
                ], 400),
            ]),
        ];
    }

    public function testDecoration(): void
    {
        $builder = new ProductSearchQueryBuilder(
            $this->getDefinition(),
            $this->createMock(TokenFilter::class),
            new Tokenizer(2),
            $this->createMock(SearchConfigLoader::class),
            $this->tokenQueryBuilder
        );

        static::expectException(DecorationPatternException::class);
        $builder->getDecorated();
    }

    private function getDefinition(): EntityDefinition
    {
        $instanceRegistry = $this->getRegistry();

        return $instanceRegistry->getByEntityName('product');
    }

    private function getRegistry(): DefinitionInstanceRegistry
    {
        return new StaticDefinitionInstanceRegistry(
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
    }

    private static function config(string $field, float $ranking, bool $tokenize = false, bool $and = true, bool $prefixMatch = true): SearchFieldConfig
    {
        return new SearchFieldConfig($field, $ranking, $tokenize, $and, $prefixMatch);
    }

    /**
     * @return array{term: array<string, array{value: string|int|float, boost: int|float}>}
     */
    private static function term(string $field, string|int|float $query, int|float $boost): array
    {
        $normalizedBoost = ($boost === 1 || $boost === 1.0) ? 1 : (float) $boost;

        return [
            'term' => [
                $field => [
                    'boost' => $normalizedBoost,
                    'value' => $query,
                ],
            ],
        ];
    }

    /**
     * @param array<mixed> $query
     * @param array<string, mixed> $explainPayload
     *
     * @return array{nested: non-empty-array<string, mixed>}
     */
    private static function nested(string $root, array $query, array $explainPayload = []): array
    {
        $nested = [
            'nested' => [
                'path' => $root,
                'query' => $query,
            ],
        ];

        if (!empty($explainPayload)) {
            $nested['nested'] = array_merge($nested['nested'], $explainPayload);
        }

        return $nested;
    }

    /**
     * @return array<mixed>
     */
    private static function match(string $field, string|int|float $query, int|float $boost, int|string|null $fuzziness = null, string $operator = 'or', ?int $maxExpansions = null): array
    {
        $payload = [
            'query' => $query,
            'boost' => (float) $boost,
            'fuzziness' => $fuzziness,
            'operator' => $operator,
            'fuzzy_transpositions' => true,
            'max_expansions' => $maxExpansions,
            'prefix_length' => 1,
        ];

        return [
            'match' => [
                $field => array_filter($payload, static fn ($value) => $value !== null),
            ],
        ];
    }

    /**
     * @param array<mixed> $queries
     *
     * @return array{dis_max: array{queries: array<mixed>}}
     */
    private static function disMax(array $queries, float|int|null $boost = null): array
    {
        $payload = [
            'queries' => $queries,
        ];

        if ($boost !== null) {
            $payload['boost'] = (float) $boost;
        }

        return [
            'dis_max' => $payload,
        ];
    }

    /**
     * @param array<mixed> $queries
     *
     * @return array{ bool: array<string, array<mixed>> }
     */
    private static function bool(array $queries): array
    {
        return [
            'bool' => [
                BoolQuery::SHOULD => $queries,
            ],
        ];
    }

    /**
     * @return array{match_phrase_prefix: array<string, array{query: string|int|float, boost: float, slop: int}>}
     */
    private static function matchPhrasePrefix(string $field, string|int|float $query, float $boost, int $slop = 3, int $maxExpansion = 10): array
    {
        return [
            'match_phrase_prefix' => [
                $field => [
                    'query' => $query,
                    'boost' => $boost,
                    'slop' => $slop,
                    'max_expansions' => $maxExpansion,
                ],
            ],
        ];
    }

    /**
     * @param array<string> $tokens
     *
     * @return array{terms: non-empty-array<string, array<string>|float|int>}
     */
    private static function terms(string $field, array $tokens, int|float $boost): array
    {
        return [
            'terms' => [
                $field => $tokens,
                'boost' => $boost,
            ],
        ];
    }

    /**
     * @return array{prefix: array<string, array{value: string|int|float, boost: float}>}
     */
    private static function prefix(string $field, string|int|float $query, float $boost): array
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
}

/**
 * @internal
 */
class CustomFieldServiceMock extends CustomFieldService
{
    /**
     * @internal
     *
     * @param array<string, Field> $config
     */
    public function __construct(private readonly array $config)
    {
    }

    public function getCustomField(string $attributeName): Field
    {
        return $this->config[$attributeName];
    }
}
