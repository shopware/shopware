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
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\SearchConfigLoader;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Filter\TokenFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\CustomField\CustomFieldService;
use Shopware\Core\System\Tag\TagDefinition;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Core\Test\Stub\Framework\Adapter\Storage\ArrayKeyValueStorage;
use Shopware\Elasticsearch\AbstractFieldQueryBuilder;
use Shopware\Elasticsearch\AbstractTokenQueryBuilder;
use Shopware\Elasticsearch\ExplainFieldQueryBuilder;
use Shopware\Elasticsearch\FieldQueryBuilder;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\ElasticsearchTokenizer;
use Shopware\Elasticsearch\NestedFieldQueryBuilder;
use Shopware\Elasticsearch\Product\ElasticsearchOptimizeSwitch;
use Shopware\Elasticsearch\Product\ProductSearchQueryBuilder;
use Shopware\Elasticsearch\Product\SearchFieldConfig;
use Shopware\Elasticsearch\TokenQueryBuilder;
use Shopware\Elasticsearch\TranslatedFieldQueryBuilder;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(AbstractTokenQueryBuilder::class)]
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
                'evolvesBool' => new BoolField('evolvesBool', 'evolvesBool'),
            ]),
            $this->createFieldQueryBuilder($storage),
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

        $expectedFuzziness = 'AUTO:5,10';
        $expectedMaxExpansions = 5;

        $nameQuery = self::disMax([
            self::exactAnalyzed('name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo', 2),
            self::match('name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo', 0.4, $expectedFuzziness, 'or', $expectedMaxExpansions),
            self::prefix('name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo', 0.4),
        ], 1000);

        $nameQuery['dis_max']['_name'] = json_encode([
            'field' => 'name',
            'term' => 'foo',
            'ranking' => 1000,
        ]);

        $tagQuery = self::disMax([
            self::exactAnalyzed('tags.name.search', 'foo', 2),
            self::match('tags.name.search', 'foo', 0.4, $expectedFuzziness, 'or', $expectedMaxExpansions),
            self::prefix('tags.name.search', 'foo', 0.4),
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
            self::config(field: 'name', ranking: 1000, tokenize: true, and: false, prefixMatch: true),
            self::config(field: 'name', ranking: 800, tokenize: true, and: true, prefixMatch: false),
            self::config(field: 'tags.name', ranking: 500, tokenize: true, and: false),
        ];

        $term = 'foo';

        $context = Context::createDefaultContext();
        $context->assign([
            'languageIdChain' => [Defaults::LANGUAGE_SYSTEM],
        ]);

        $query = $this->tokenQueryBuilder->build('product', $term, $config, $context);

        static::assertNotNull($query);

        $expectedFuzziness = 'AUTO:5,10';
        $expectedMaxExpansions = 5;

        $expected = self::bool([
            self::disMax([
                self::exactAnalyzed('name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo', 2),
                self::match('name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo', 0.4, $expectedFuzziness, 'or', $expectedMaxExpansions),
                self::prefix('name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo', 0.4),
            ], 1000),
            self::disMax([
                self::exactAnalyzed('name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo', 2),
                self::match('name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo', 0.4, $expectedFuzziness, 'and', $expectedMaxExpansions),
            ], 800),
            self::nested('tags', self::disMax([
                self::exactAnalyzed('tags.name.search', 'foo', 2),
                self::match('tags.name.search', 'foo', 0.4, $expectedFuzziness, 'or', $expectedMaxExpansions),
                self::prefix('tags.name.search', 'foo', 0.4),
            ], 500)),
        ]);

        static::assertSame($expected, $query->toArray());
    }

    /**
     * @param list<SearchFieldConfig> $config
     * @param array<string, mixed> $expected
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
     * @param list<SearchFieldConfig> $config
     * @param array<string, mixed>|null $expected
     */
    #[DataProvider('buildMultipleLanguageProvider')]
    public function testBuildMultipleLanguages(array $config, string $term, ?array $expected): void
    {
        $context = Context::createDefaultContext();
        $context->assign([
            'languageIdChain' => [Defaults::LANGUAGE_SYSTEM, self::SECOND_LANGUAGE_ID],
        ]);

        $query = $this->tokenQueryBuilder->build('product', $term, $config, $context);

        if ($expected === null) {
            static::assertNull($query);

            return;
        }

        static::assertNotNull($query);
        static::assertSame($expected, $query->toArray());
    }

    /**
     * @return \Generator<array{config: list<SearchFieldConfig>, term: string, expected: array<string, mixed>}>
     */
    public static function buildSingleLanguageProvider(): \Generator
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
                    self::exactAnalyzed('name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo', 2),
                    self::match('name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo', 0.4, 'AUTO:5,10', 'or', 5),
                    self::prefix('name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo', 0.4),
                ], 1000),
                self::nested('tags', self::disMax([
                    self::exactAnalyzed('tags.name.search', 'foo', 2),
                    self::match('tags.name.search', 'foo', 0.4, 'AUTO:5,10', 'or', 5),
                    self::prefix('tags.name.search', 'foo', 0.4),
                ], 500)),
            ]),
        ];

        yield 'Test term is normalized' => [
            'config' => [
                self::config(field: 'name', ranking: 1000, tokenize: true, and: false),
            ],
            'term' => ' FoO ',
            'expected' => self::disMax([
                self::exactAnalyzed('name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo', 2),
                self::match('name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo', 0.4, 'AUTO:5,10', 'or', 5),
                self::prefix('name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo', 0.4),
            ], 1000),
        ];

        yield 'Test term with spaces is normalized' => [
            'config' => [
                self::config(field: 'name', ranking: 1000, tokenize: true, and: false),
            ],
            'term' => ' FoO     BaR    Baz    ',
            'expected' => self::disMax([
                self::terms('name.' . Defaults::LANGUAGE_SYSTEM, ['foo', 'bar', 'baz'], 2),
                self::match('name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo bar baz', 0.4, 'AUTO:5,10', 'or', 5),
                self::matchPhrasePrefix('name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo bar baz', 0.6, 3, 5),
            ], 1000),
        ];

        yield 'Tokenized field uses ngram match for long term' => [
            'config' => [
                self::config(field: 'name', ranking: 1000, tokenize: true, and: false),
            ],
            'term' => 'foooooooooo',
            'expected' => self::disMax([
                self::exactAnalyzed('name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foooooooooo', 2),
                self::match('name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foooooooooo', 0.4, 'AUTO:5,10', 'or', 20),
                self::prefix('name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foooooooooo', 0.4),
                self::matchSimple('name.' . Defaults::LANGUAGE_SYSTEM . '.ngram', 'foooooooooo', 0.4),
            ], 1000),
        ];

        yield 'Test multiple fields OR search' => [
            'config' => [
                self::config(field: 'name', ranking: 1000, and: false),
                self::config(field: 'ean', ranking: 2000, and: false),
                self::config(field: 'restockTime', ranking: 1500, and: false),
                self::config(field: 'tags.name', ranking: 500, and: false),
            ],
            'term' => 'foo 2023',
            'expected' => self::bool([
                self::disMax([
                    self::terms('name.' . Defaults::LANGUAGE_SYSTEM, ['foo', '2023'], 2),
                    self::match('name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo 2023', 0.4, 0, 'or', 10),
                    self::matchPhrasePrefix('name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo 2023', 0.6, 3, 10),
                ], 1000),
                self::disMax([
                    self::terms('ean', ['foo', '2023'], 2),
                    self::match('ean.search', 'foo 2023', 0.4, 0, 'or', 10),
                    self::matchPhrasePrefix('ean.search', 'foo 2023', 0.6, 3, 10),
                ], 2000),
                self::nested('tags', self::disMax([
                    self::terms('tags.name', ['foo', '2023'], 2),
                    self::match('tags.name.search', 'foo 2023', 0.4, 0, 'or', 10),
                    self::matchPhrasePrefix('tags.name.search', 'foo 2023', 0.6, 3, 10),
                ], 500)),
            ]),
        ];

        yield 'Test multiple fields AND search' => [
            'config' => [
                self::config(field: 'name', ranking: 1000),
                self::config(field: 'ean', ranking: 2000),
                self::config(field: 'restockTime', ranking: 1500),
                self::config(field: 'tags.name', ranking: 500),
            ],
            'term' => 'foo 2023',
            'expected' => self::bool([
                self::disMax([
                    self::must('name.' . Defaults::LANGUAGE_SYSTEM, ['foo', '2023'], 2),
                    self::match('name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo 2023', 0.4, 0, 'and', 10),
                    self::matchPhrasePrefix('name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo 2023', 0.6, 3, 10),
                ], 1000),
                self::disMax([
                    self::must('ean', ['foo', '2023'], 2),
                    self::match('ean.search', 'foo 2023', 0.4, 0, 'and', 10),
                    self::matchPhrasePrefix('ean.search', 'foo 2023', 0.6, 3, 10),
                ], 2000),
                self::nested('tags', self::disMax([
                    self::must('tags.name', ['foo', '2023'], 2),
                    self::match('tags.name.search', 'foo 2023', 0.4, 0, 'and', 10),
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
                    self::exactAnalyzed($prefix . 'evolvesText.search', '2023', 2),
                    self::match($prefix . 'evolvesText.search', '2023', 0.4, 0, 'and', 10),
                    self::prefix($prefix . 'evolvesText.search', '2023', 0.4),
                ], 500),
                self::term($prefix . 'evolvesInt', 2023, 400),
                self::term($prefix . 'evolvesFloat', 2023.0, 500),
                self::nested('categories', self::term('categories.childCount', 2023, 500)),
            ]),
        ];
    }

    /**
     * @return iterable<array-key, array{config: list<SearchFieldConfig>, term: string, expected: array<string, mixed>|null}>
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
                    self::exactAnalyzed('name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo', 2),
                    self::match('name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo', 0.4, 'AUTO:5,10', 'or', 5),
                    self::prefix('name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo', 0.4),
                ], 1000),
                self::nested('tags', self::disMax([
                    self::exactAnalyzed('tags.name.search', 'foo', 2),
                    self::match('tags.name.search', 'foo', 0.4, 'AUTO:5,10', 'or', 5),
                    self::prefix('tags.name.search', 'foo', 0.4),
                ], 500)),
                self::nested('categories', self::disMax([
                    self::disMax([
                        self::exactAnalyzed('categories.name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo', 2),
                        self::match('categories.name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo', 0.4, 'AUTO:5,10', 'or', 5),
                        self::prefix('categories.name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo', 0.4),
                    ], 200),
                    self::disMax([
                        self::exactAnalyzed('categories.name.' . self::SECOND_LANGUAGE_ID . '.search', 'foo', 2),
                        self::match('categories.name.' . self::SECOND_LANGUAGE_ID . '.search', 'foo', 0.4, 'AUTO:5,10', 'or', 5),
                        self::prefix('categories.name.' . self::SECOND_LANGUAGE_ID . '.search', 'foo', 0.4),
                    ], 160),
                ])),
            ]),
        ];

        yield 'Test multiple fields with terms OR search' => [
            'config' => [
                self::config(field: 'name', ranking: 1000, and: false),
                self::config(field: 'ean', ranking: 2000, and: false),
                self::config(field: 'restockTime', ranking: 1500, and: false),
                self::config(field: 'tags.name', ranking: 500, and: false),
            ],
            'term' => 'foo 2023',
            'expected' => self::bool([
                self::disMax([
                    self::terms('name.' . Defaults::LANGUAGE_SYSTEM, ['foo', '2023'], 2),
                    self::match('name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo 2023', 0.4, 0, 'or', 10),
                    self::matchPhrasePrefix('name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo 2023', 0.6, 3, 10),
                ], 1000),
                self::disMax([
                    self::terms('ean', ['foo', '2023'], 2),
                    self::match('ean.search', 'foo 2023', 0.4, 0, 'or', 10),
                    self::matchPhrasePrefix('ean.search', 'foo 2023', 0.6, 3, 10),
                ], 2000),
                self::nested('tags', self::disMax([
                    self::terms('tags.name', ['foo', '2023'], 2),
                    self::match('tags.name.search', 'foo 2023', 0.4, 0, 'or', 10),
                    self::matchPhrasePrefix('tags.name.search', 'foo 2023', 0.6, 3, 10),
                ], 500)),
            ]),
        ];

        yield 'Test multiple fields with terms AND search' => [
            'config' => [
                self::config(field: 'name', ranking: 1000),
                self::config(field: 'ean', ranking: 2000),
                self::config(field: 'restockTime', ranking: 1500),
                self::config(field: 'tags.name', ranking: 500),
            ],
            'term' => 'foo 2023',
            'expected' => self::bool([
                self::disMax([
                    self::must('name.' . Defaults::LANGUAGE_SYSTEM, ['foo', '2023'], 2),
                    self::match('name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo 2023', 0.4, 0, 'and', 10),
                    self::matchPhrasePrefix('name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo 2023', 0.6, 3, 10),
                ], 1000),
                self::disMax([
                    self::must('ean', ['foo', '2023'], 2),
                    self::match('ean.search', 'foo 2023', 0.4, 0, 'and', 10),
                    self::matchPhrasePrefix('ean.search', 'foo 2023', 0.6, 3, 10),
                ], 2000),
                self::nested('tags', self::disMax([
                    self::must('tags.name', ['foo', '2023'], 2),
                    self::match('tags.name.search', 'foo 2023', 0.4, 0, 'and', 10),
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
                        self::exactAnalyzed($prefixCfLang1 . 'evolvesText.search', '2023', 2),
                        self::match($prefixCfLang1 . 'evolvesText.search', '2023', 0.4, 0, 'and', 10),
                        self::prefix($prefixCfLang1 . 'evolvesText.search', '2023', 0.4),
                    ], 500),
                    self::disMax([
                        self::exactAnalyzed($prefixCfLang2 . 'evolvesText.search', '2023', 2),
                        self::match($prefixCfLang2 . 'evolvesText.search', '2023', 0.4, 0, 'and', 10),
                        self::prefix($prefixCfLang2 . 'evolvesText.search', '2023', 0.4),
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
                    self::exactAnalyzed($prefixCfLang1 . 'evolvesText.search', 'foo', 2),
                    self::match($prefixCfLang1 . 'evolvesText.search', 'foo', 0.4, 'AUTO:5,10', 'and', 5),
                    self::prefix($prefixCfLang1 . 'evolvesText.search', 'foo', 0.4),
                ], 500),
                self::disMax([
                    self::exactAnalyzed($prefixCfLang2 . 'evolvesText.search', 'foo', 2),
                    self::match($prefixCfLang2 . 'evolvesText.search', 'foo', 0.4, 'AUTO:5,10', 'and', 5),
                    self::prefix($prefixCfLang2 . 'evolvesText.search', 'foo', 0.4),
                ], 400),
            ]),
        ];

        yield 'Test bool custom field with true term' => [
            'config' => [
                self::config(field: 'customFields.evolvesBool', ranking: 500),
            ],
            'term' => 'true',
            'expected' => self::disMax([
                self::term($prefixCfLang1 . 'evolvesBool', true, 500),
                self::term($prefixCfLang2 . 'evolvesBool', true, 400),
            ]),
        ];

        yield 'Test bool custom field with false term' => [
            'config' => [
                self::config(field: 'customFields.evolvesBool', ranking: 500),
            ],
            'term' => 'false',
            'expected' => self::disMax([
                self::term($prefixCfLang1 . 'evolvesBool', false, 500),
                self::term($prefixCfLang2 . 'evolvesBool', false, 400),
            ]),
        ];

        yield 'Test bool custom field with 1 term' => [
            'config' => [
                self::config(field: 'customFields.evolvesBool', ranking: 500),
            ],
            'term' => '1',
            'expected' => self::disMax([
                self::term($prefixCfLang1 . 'evolvesBool', true, 500),
                self::term($prefixCfLang2 . 'evolvesBool', true, 400),
            ]),
        ];

        yield 'Test bool custom field with 0 term' => [
            'config' => [
                self::config(field: 'customFields.evolvesBool', ranking: 500),
            ],
            'term' => '0',
            'expected' => self::disMax([
                self::term($prefixCfLang1 . 'evolvesBool', false, 500),
                self::term($prefixCfLang2 . 'evolvesBool', false, 400),
            ]),
        ];

        yield 'Test bool custom field with non-boolean text term returns null' => [
            'config' => [
                self::config(field: 'customFields.evolvesBool', ranking: 500),
            ],
            'term' => 'hello',
            'expected' => null,
        ];

        yield 'Test non-boolean text term skips bool field but matches text field' => [
            'config' => [
                self::config(field: 'customFields.evolvesBool', ranking: 600),
                self::config(field: 'customFields.evolvesText', ranking: 500),
                self::config(field: 'customFields.evolvesInt', ranking: 400),
                self::config(field: 'customFields.evolvesFloat', ranking: 500),
            ],
            'term' => 'foo',
            'expected' => self::disMax([
                self::disMax([
                    self::exactAnalyzed($prefixCfLang1 . 'evolvesText.search', 'foo', 2),
                    self::match($prefixCfLang1 . 'evolvesText.search', 'foo', 0.4, 'AUTO:5,10', 'and', 5),
                    self::prefix($prefixCfLang1 . 'evolvesText.search', 'foo', 0.4),
                ], 500),
                self::disMax([
                    self::exactAnalyzed($prefixCfLang2 . 'evolvesText.search', 'foo', 2),
                    self::match($prefixCfLang2 . 'evolvesText.search', 'foo', 0.4, 'AUTO:5,10', 'and', 5),
                    self::prefix($prefixCfLang2 . 'evolvesText.search', 'foo', 0.4),
                ], 400),
            ]),
        ];
    }

    public function testBuildWithLanguageAnalyzerDisabled(): void
    {
        $storage = new ArrayKeyValueStorage([ElasticsearchOptimizeSwitch::FLAG => true]);
        $tokenQueryBuilder = new TokenQueryBuilder(
            $this->getRegistry(),
            new CustomFieldServiceMock([
                'evolvesInt' => new IntField('evolvesInt', 'evolvesInt'),
                'evolvesFloat' => new FloatField('evolvesFloat', 'evolvesFloat'),
                'evolvesText' => new StringField('evolvesText', 'evolvesText'),
            ]),
            $this->createFieldQueryBuilder($storage, false),
        );

        $context = Context::createDefaultContext();
        $context->assign([
            'languageIdChain' => [Defaults::LANGUAGE_SYSTEM],
        ]);

        $config = [
            self::config(field: 'name', ranking: 1000, tokenize: true, and: false),
        ];

        $term = 'foo bar';
        $query = $tokenQueryBuilder->build('product', $term, $config, $context);

        static::assertNotNull($query);
        $queryArray = $query->toArray();

        $matchQuery = $queryArray['dis_max']['queries'][1]['match'] ?? null;
        static::assertNotNull($matchQuery);
        $searchField = 'name.' . Defaults::LANGUAGE_SYSTEM . '.search';
        static::assertArrayHasKey($searchField, $matchQuery);
        static::assertSame('sw_whitespace_analyzer', $matchQuery[$searchField]['analyzer'] ?? null);

        $matchPhrasePrefixQuery = $queryArray['dis_max']['queries'][2]['match_phrase_prefix'] ?? null;
        static::assertNotNull($matchPhrasePrefixQuery);
        static::assertArrayHasKey($searchField, $matchPhrasePrefixQuery);
        static::assertSame('sw_whitespace_analyzer', $matchPhrasePrefixQuery[$searchField]['analyzer'] ?? null);
    }

    public function testDecoration(): void
    {
        $builder = new ProductSearchQueryBuilder(
            $this->getDefinition(),
            $this->createMock(TokenFilter::class),
            $this->createMock(SearchConfigLoader::class),
            $this->tokenQueryBuilder,
            new ElasticsearchTokenizer(),
        );

        static::expectException(DecorationPatternException::class);
        $builder->getDecorated();
    }

    public function testTokenQueryBuilderDecoration(): void
    {
        static::expectException(DecorationPatternException::class);
        $this->tokenQueryBuilder->getDecorated();
    }

    public function testTieBreakerRewardsMultiClauseMatchWithinField(): void
    {
        $config = [
            self::config(field: 'name', ranking: 1000, tokenize: true, and: false),
        ];

        $context = Context::createDefaultContext();
        $context->assign(['languageIdChain' => [Defaults::LANGUAGE_SYSTEM]]);

        $query = $this->tokenQueryBuilder->build('product', 'stihl', $config, $context);
        static::assertNotNull($query);

        $queryArray = $query->toArray();

        static::assertSame(0.2, $queryArray['dis_max']['tie_breaker']);
        static::assertCount(4, $queryArray['dis_max']['queries']);

        static::assertArrayHasKey('match', $queryArray['dis_max']['queries'][0]);
        static::assertArrayHasKey('match', $queryArray['dis_max']['queries'][1]);
        static::assertArrayHasKey('match_bool_prefix', $queryArray['dis_max']['queries'][2]);
        static::assertArrayHasKey('match', $queryArray['dis_max']['queries'][3]);
    }

    public function testTieBreakerRewardsMultiLanguageMatch(): void
    {
        $config = [
            self::config(field: 'categories.name', ranking: 200, tokenize: true, and: false),
        ];

        $context = Context::createDefaultContext();
        $context->assign(['languageIdChain' => [Defaults::LANGUAGE_SYSTEM, self::SECOND_LANGUAGE_ID]]);

        $query = $this->tokenQueryBuilder->build('product', 'foo', $config, $context);
        static::assertNotNull($query);

        $queryArray = $query->toArray();

        static::assertArrayHasKey('nested', $queryArray);
        $outerDisMax = $queryArray['nested']['query']['dis_max'];

        static::assertSame(0.2, $outerDisMax['tie_breaker']);
        static::assertCount(2, $outerDisMax['queries']);

        $lang1Query = $outerDisMax['queries'][0];
        $lang2Query = $outerDisMax['queries'][1];

        static::assertSame(0.2, $lang1Query['dis_max']['tie_breaker']);
        static::assertSame(0.2, $lang2Query['dis_max']['tie_breaker']);

        static::assertSame(200.0, $lang1Query['dis_max']['boost']);
        static::assertSame(160.0, $lang2Query['dis_max']['boost']);
    }

    public function testTieBreakerAcrossMultipleFields(): void
    {
        $config = [
            self::config(field: 'name', ranking: 1000, tokenize: true, and: false),
            self::config(field: 'tags.name', ranking: 500, tokenize: true, and: false),
        ];

        $context = Context::createDefaultContext();
        $context->assign(['languageIdChain' => [Defaults::LANGUAGE_SYSTEM]]);

        $query = $this->tokenQueryBuilder->build('product', 'stihl', $config, $context);
        static::assertNotNull($query);

        $queryArray = $query->toArray();

        static::assertArrayHasKey('bool', $queryArray);
        $shouldClauses = $queryArray['bool']['should'];
        static::assertCount(2, $shouldClauses);

        $nameDisMax = $shouldClauses[0]['dis_max'];
        static::assertSame(0.2, $nameDisMax['tie_breaker']);
        static::assertSame(1000.0, $nameDisMax['boost']);

        $tagsNested = $shouldClauses[1]['nested']['query']['dis_max'];
        static::assertSame(0.2, $tagsNested['tie_breaker']);
        static::assertSame(500.0, $tagsNested['boost']);
    }

    public function testTieBreakerWithNgramClause(): void
    {
        $config = [
            self::config(field: 'name', ranking: 1000, tokenize: true, and: false),
        ];

        $context = Context::createDefaultContext();
        $context->assign(['languageIdChain' => [Defaults::LANGUAGE_SYSTEM]]);

        $query = $this->tokenQueryBuilder->build('product', 'foooooooooo', $config, $context);
        static::assertNotNull($query);

        $queryArray = $query->toArray();

        static::assertSame(0.2, $queryArray['dis_max']['tie_breaker']);
        static::assertCount(4, $queryArray['dis_max']['queries']);
    }

    public function testFieldBuilderDecorationOrder(): void
    {
        $storage = new ArrayKeyValueStorage([ElasticsearchOptimizeSwitch::FLAG => true]);

        $builder = $this->createFieldQueryBuilder($storage);

        static::assertInstanceOf(ExplainFieldQueryBuilder::class, $builder);
        static::assertInstanceOf(NestedFieldQueryBuilder::class, $builder->getDecorated());
        static::assertInstanceOf(TranslatedFieldQueryBuilder::class, $builder->getDecorated()->getDecorated());
        static::assertInstanceOf(FieldQueryBuilder::class, $builder->getDecorated()->getDecorated()->getDecorated());
    }

    private function createFieldQueryBuilder(ArrayKeyValueStorage $storage, bool $useLanguageAnalyzer = true): AbstractFieldQueryBuilder
    {
        return new ExplainFieldQueryBuilder(
            new NestedFieldQueryBuilder(
                new TranslatedFieldQueryBuilder(
                    new FieldQueryBuilder(4, $useLanguageAnalyzer),
                    $storage,
                ),
            ),
        );
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
     * @return array{term: array<string, array{value: string|int|float|bool, boost: int|float}>}
     */
    private static function term(string $field, string|int|float|bool $query, int|float $boost): array
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
     * @return array{match: array<string, array{query: string|int|float, boost: int|float, fuzziness: int, operator: string}>}
     */
    private static function exactAnalyzed(string $field, string|int|float $query, int|float $boost): array
    {
        return [
            'match' => [
                $field => [
                    'query' => $query,
                    'boost' => $boost,
                    'fuzziness' => 0,
                    'operator' => 'and',
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

        if ($explainPayload !== []) {
            $nested['nested'] = array_merge($nested['nested'], $explainPayload);
        }

        return $nested;
    }

    /**
     * @return array<mixed>
     */
    private static function match(string $field, string|int|float $query, int|float $boost, int|string|null $fuzziness = null, string $operator = 'or', ?int $maxExpansions = null, ?string $analyzer = null): array
    {
        $payload = [
            'query' => $query,
            'boost' => (float) $boost,
            'fuzziness' => $fuzziness,
            'operator' => $operator,
            'fuzzy_transpositions' => true,
            'max_expansions' => $maxExpansions,
            'prefix_length' => mb_strlen((string) $query) >= 10 ? 3 : 2,
            'analyzer' => $analyzer,
        ];

        return [
            'match' => [
                $field => array_filter($payload, static fn ($value) => $value !== null),
            ],
        ];
    }

    /**
     * @return array{match: array<string, array{query: string, boost: float}>}
     */
    private static function matchSimple(string $field, string $query, float $boost): array
    {
        return [
            'match' => [
                $field => [
                    'query' => $query,
                    'boost' => $boost,
                ],
            ],
        ];
    }

    /**
     * @param array<mixed> $queries
     *
     * @return array{dis_max: array{queries: array<mixed>}}
     */
    private static function disMax(array $queries, float|int|null $boost = null, ?float $tieBreaker = 0.2): array
    {
        $payload = [
            'queries' => $queries,
        ];

        if ($boost !== null) {
            $payload['boost'] = (float) $boost;
        }

        if ($tieBreaker !== null) {
            $payload['tie_breaker'] = $tieBreaker;
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
     * @param array<string> $tokens
     *
     * @return array{bool: array{must: array<array{term: array<string, string>}>, boost: float|int}}
     */
    private static function must(string $field, array $tokens, int|float $boost = 2): array
    {
        $queries = array_map(static fn (string $token) => ['term' => [$field => $token]], $tokens);

        return [
            'bool' => [
                BoolQuery::MUST => $queries,
                'boost' => $boost,
            ],
        ];
    }

    /**
     * @param array<string> $tokens
     *
     * @return array{terms: non-empty-array<string, array<string>|float|int>}
     */
    private static function terms(string $field, array $tokens, int|float $boost = 2): array
    {
        return [
            'terms' => [
                $field => $tokens,
                'boost' => $boost,
            ],
        ];
    }

    /**
     * @return array{match_bool_prefix: array<string, array{query: string|int|float, boost: float}>}
     */
    private static function prefix(string $field, string|int|float $query, float $boost = 1): array
    {
        return [
            'match_bool_prefix' => [
                $field => [
                    'query' => $query,
                    'boost' => $boost,
                ],
            ],
        ];
    }

    /**
     * @return array{match_phrase_prefix: array<string, array{query: string|int|float, boost: float, slop: int, max_expansions: int}>}
     */
    private static function matchPhrasePrefix(string $field, string|int|float $query, float $boost, int $slop = 3, int $maxExpansions = 10): array
    {
        return [
            'match_phrase_prefix' => [
                $field => [
                    'query' => $query,
                    'boost' => $boost,
                    'slop' => $slop,
                    'max_expansions' => $maxExpansions,
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
