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
        ], []);
        static::assertNull($query);
    }

    public function testBuildWithoutFields(): void
    {
        $query = $this->tokenQueryBuilder->build('product', 'foo', [], []);
        static::assertNull($query);
    }

    /**
     * @param SearchFieldConfig[] $config
     * @param array{string: mixed} $expected
     */
    #[DataProvider('buildSingleLanguageProvider')]
    public function testBuildSingleLanguage(array $config, string $term, array $expected): void
    {
        $query = $this->tokenQueryBuilder->build('product', $term, $config, [Defaults::LANGUAGE_SYSTEM]);

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
        $query = $this->tokenQueryBuilder->build('product', $term, $config, [Defaults::LANGUAGE_SYSTEM, self::SECOND_LANGUAGE_ID]);

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
                self::textMatch('name', 'foo', 1000, Defaults::LANGUAGE_SYSTEM),
                self::nested('tags', self::textMatch('tags.name', 'foo', 500)),
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
                self::term($prefix . 'evolvesInt', 2023, 2000),
                self::term($prefix . 'evolvesFloat', 2023.0, 2500),
                self::nested('categories', self::term('categories.childCount', 2023, 2500)),
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
                    self::textMatch('name', 'foo', 1000, Defaults::LANGUAGE_SYSTEM),
                    self::textMatch('name', 'foo', 800, self::SECOND_LANGUAGE_ID),
                ]),
                self::nested('tags', self::textMatch('tags.name', 'foo', 500)),
                self::nested('categories', self::disMax([
                    self::textMatch('categories.name', 'foo', 200, Defaults::LANGUAGE_SYSTEM),
                    self::textMatch('categories.name', 'foo', 160, self::SECOND_LANGUAGE_ID),
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
                    self::term($prefixCfLang1 . 'evolvesInt', 2023, 2000),
                    self::term($prefixCfLang2 . 'evolvesInt', 2023, 1600),
                ]),
                self::disMax([
                    self::term($prefixCfLang1 . 'evolvesFloat', 2023, 2500),
                    self::term($prefixCfLang2 . 'evolvesFloat', 2023, 2000),
                ]),
                self::nested('categories', self::term('categories.childCount', 2023, 2500)),
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
     * @return array<mixed>
     */
    private static function textMatch(string $field, string|int|float $query, int|float $boost, ?string $languageId = null, ?bool $tokenized = true): array
    {
        if ($languageId !== null) {
            $field .= '.' . $languageId;
        }

        $queries = [
            self::match($field . '.search', $query, $boost * 5),
            self::matchPhrasePrefix($field . '.search', $query, $boost * 4),
        ];

        if ($tokenized) {
            $queries[] = self::match($field . '.search', $query, $boost * 3, 'auto');
            $queries[] = self::match($field . '.ngram', $query, $boost * 2, null);
        } else {
            $queries[] = self::match($field . '.search', $query, $boost * 3, 1);
        }

        return self::bool($queries);
    }

    /**
     * @return array{match: array<string, array{query: string|int|float, boost: float, fuzziness?: int|string|null}>}
     */
    private static function match(string $field, string|int|float $query, int|float $boost, int|string|null $fuzziness = 0): array
    {
        $payload = [
            'query' => $query,
            'boost' => (float) $boost,
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
