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
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Filter\TokenFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Tokenizer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\CustomField\CustomFieldService;
use Shopware\Core\System\Tag\TagDefinition;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\ElasticsearchEntitySearcher;
use Shopware\Elasticsearch\Product\ProductSearchQueryBuilder;
use Shopware\Elasticsearch\Product\SearchConfigLoader;
use Shopware\Elasticsearch\Product\SearchFieldConfig;
use Shopware\Elasticsearch\TokenQueryBuilder;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(TokenQueryBuilder::class)]
class TokenQueryBuilderTest extends TestCase
{
    private const SECOND_LANGUAGE_ID = '2fbb5fe2e29a4d70aa5854ce7ce3e20c';

    private TokenQueryBuilder $tokenQueryBuilder;

    protected function setUp(): void
    {
        $this->tokenQueryBuilder = new TokenQueryBuilder(
            $this->getRegistry(),
            new CustomFieldServiceMock([
                'evolvesInt' => new IntField('evolvesInt', 'evolvesInt'),
                'evolvesFloat' => new FloatField('evolvesFloat', 'evolvesFloat'),
                'evolvesText' => new StringField('evolvesText', 'evolvesText'),
            ])
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

        $context->addState(ElasticsearchEntitySearcher::EXPLAIN_MODE);

        $query = $this->tokenQueryBuilder->build('product', $term, $config, $context);

        static::assertNotNull($query);

        $expected = self::bool([
            self::textMatch(field: 'name', query: 'foo', boost: 1000, languageId: Defaults::LANGUAGE_SYSTEM, andSearch: false, explain: true),
            self::nested(root: 'tags', query: self::textMatch(field: 'tags.name', query: 'foo', boost: 500, andSearch: false), explainPayload: [
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
        static::assertEquals($expected, $query->toArray());
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
                self::textMatch(field: 'name', query: 'foo', boost: 1000, languageId: Defaults::LANGUAGE_SYSTEM, andSearch: false),
                self::nested('tags', self::textMatch(field: 'tags.name', query: 'foo', boost: 500, andSearch: false)),
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
                self::textMatch('name', 'foo 2023', 1000, Defaults::LANGUAGE_SYSTEM, false),
                self::textMatch('ean', 'foo 2023', 2000, null, false),
                self::nested('tags', self::textMatch('tags.name', 'foo 2023', 500, null, false)),
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
                self::textMatch($prefix . 'evolvesText', '2023', 500, null, false),
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
                    self::textMatch(field: 'name', query: 'foo', boost: 1000, languageId: Defaults::LANGUAGE_SYSTEM, andSearch: false),
                    self::textMatch('name', 'foo', 800, self::SECOND_LANGUAGE_ID, andSearch: false),
                ]),
                self::nested('tags', self::textMatch('tags.name', 'foo', 500, andSearch: false)),
                self::nested('categories', self::disMax([
                    self::textMatch('categories.name', 'foo', 200, Defaults::LANGUAGE_SYSTEM, andSearch: false),
                    self::textMatch('categories.name', 'foo', 160, self::SECOND_LANGUAGE_ID, andSearch: false),
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
                    self::textMatch('name', 'foo 2023', 1000, Defaults::LANGUAGE_SYSTEM, false),
                    self::textMatch('name', 'foo 2023', 800, self::SECOND_LANGUAGE_ID, false),
                ]),
                self::textMatch('ean', 'foo 2023', 2000, null, false),
                self::nested('tags', self::textMatch('tags.name', 'foo 2023', 500, null, false)),
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
                    self::textMatch($prefixCfLang1 . 'evolvesText', '2023', 500, null, false),
                    self::textMatch($prefixCfLang2 . 'evolvesText', '2023', 400, null, false),
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
                self::textMatch($prefixCfLang1 . 'evolvesText', 'foo', 500, null, false),
                self::textMatch($prefixCfLang2 . 'evolvesText', 'foo', 400, null, false),
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

    private static function config(string $field, float $ranking, bool $tokenize = false, bool $and = true): SearchFieldConfig
    {
        return new SearchFieldConfig($field, $ranking, $tokenize, $and);
    }

    /**
     * @return array{term: array<string, array{value: string|int|float, boost: int|float}>}
     */
    private static function term(string $field, string|int|float $query, int|float $boost): array
    {
        return [
            'term' => [
                $field => [
                    'boost' => (float) $boost,
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
    private static function textMatch(string $field, string|int|float $query, int|float $boost, ?string $languageId = null, ?bool $tokenized = true, ?bool $andSearch = true, bool $explain = false): array
    {
        $languageField = $field;

        if ($languageId !== null) {
            $languageField .= '.' . $languageId;
        }

        $tokenCount = \count(\explode(' ', (string) $query));

        $queries = [
            self::match($languageField . '.search', $query, $boost, $tokenized ? 'auto' : 1, $andSearch),
            self::matchPhrasePrefix($languageField . '.search', $query, $boost * 0.6),
        ];

        if ($tokenized && $tokenCount === 1) {
            $queries[] = self::match($languageField . '.ngram', $query, $boost * 0.4, null, $andSearch);
        }

        $dismax = self::disMax($queries);

        if ($explain) {
            $dismax['dis_max']['_name'] = json_encode([
                'field' => $field,
                'term' => $query,
                'ranking' => $boost,
            ]);
        }

        return $dismax;
    }

    /**
     * @return array{match: array<string, array{query: string|int|float, boost: float, fuzziness?: int|string|null}>}
     */
    private static function match(string $field, string|int|float $query, int|float $boost, int|string|null $fuzziness = 0, ?bool $andSearch = false): array
    {
        $payload = [
            'query' => $query,
            'boost' => (float) $boost,
        ];

        if ($fuzziness !== null) {
            $payload['fuzziness'] = $fuzziness;
        }

        if (!\str_contains($field, '.ngram')) {
            $payload['operator'] = $andSearch ? 'and' : 'or';
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
    private static function matchPhrasePrefix(string $field, string|int|float $query, float $boost, int $slop = 3): array
    {
        return [
            'match_phrase_prefix' => [
                $field => [
                    'query' => $query,
                    'boost' => $boost,
                    'slop' => $slop,
                    'max_expansions' => 10,
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

    public function getCustomField(string $attributeName): ?Field
    {
        return $this->config[$attributeName] ?? null;
    }
}
