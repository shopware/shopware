<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Product;

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
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\SearchConfigLoader;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Filter\AbstractTokenFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Filter\TokenFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Tokenizer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\CustomField\CustomFieldService;
use Shopware\Core\System\Tag\TagDefinition;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Core\Test\Stub\Framework\Adapter\Storage\ArrayKeyValueStorage;
use Shopware\Elasticsearch\ElasticsearchException;
use Shopware\Elasticsearch\ExplainFieldQueryBuilder;
use Shopware\Elasticsearch\FieldQueryBuilder;
use Shopware\Elasticsearch\NestedFieldQueryBuilder;
use Shopware\Elasticsearch\Product\AbstractProductSearchQueryBuilder;
use Shopware\Elasticsearch\Product\ElasticsearchOptimizeSwitch;
use Shopware\Elasticsearch\Product\ProductSearchQueryBuilder;
use Shopware\Elasticsearch\TokenQueryBuilder;
use Shopware\Elasticsearch\TranslatedFieldQueryBuilder;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(AbstractProductSearchQueryBuilder::class)]
#[CoversClass(ProductSearchQueryBuilder::class)]
#[Package('inventory')]
class ProductSearchQueryBuilderTest extends TestCase
{
    private const SECOND_LANGUAGE_ID = '2fbb5fe2e29a4d70aa5854ce7ce3e20c';

    private TokenQueryBuilder $tokenQueryBuilder;

    protected function setUp(): void
    {
        $storage = new ArrayKeyValueStorage([
            ElasticsearchOptimizeSwitch::FLAG => true,
        ]);

        $this->tokenQueryBuilder = new TokenQueryBuilder(
            $this->getRegistry(),
            new CustomFieldServiceStub([
                'evolvesInt' => new IntField('evolvesInt', 'evolvesInt'),
                'evolvesFloat' => new FloatField('evolvesFloat', 'evolvesFloat'),
                'evolvesText' => new StringField('evolvesText', 'evolvesText'),
            ]),
            new ExplainFieldQueryBuilder(
                new NestedFieldQueryBuilder(
                    new TranslatedFieldQueryBuilder(
                        new FieldQueryBuilder(),
                        $storage,
                    ),
                ),
            ),
        );
    }

    public function testBuildEmptyQuery(): void
    {
        static::expectException(ElasticsearchException::class);
        static::expectExceptionMessage('Empty query provided');

        $builder = $this->getBuilder([
            self::config(field: 'restockTime', ranking: 500, tokenize: true, and: false),
        ]);

        $criteria = new Criteria();
        $criteria->setTerm('foo');
        $parsed = $builder->build($criteria, Context::createDefaultContext());

        static::assertSame([], $parsed->toArray());
    }

    public function testBuildWithoutFields(): void
    {
        static::expectException(ElasticsearchException::class);
        static::expectExceptionMessage('Empty query provided');

        $builder = $this->getBuilder(null);

        $criteria = new Criteria();

        $parsed = $builder->build($criteria, Context::createDefaultContext());

        static::assertSame([], $parsed->toArray());
    }

    /**
     * @param array{array{and_logic: string, field: string, tokenize: int, ranking: float}} $config
     * @param array<string, mixed> $expected
     */
    #[DataProvider('buildSingleLanguageProvider')]
    public function testBuildSingleLanguage(array $config, string $term, array $expected): void
    {
        $builder = $this->getBuilder($config);

        $criteria = new Criteria();
        $criteria->setTerm($term);

        $parsed = $builder->build($criteria, Context::createDefaultContext());

        static::assertEquals($expected, $parsed->toArray());
    }

    /**
     * @param array{array{and_logic: string, field: string, tokenize: int, ranking: int|float}} $config
     * @param array<string, mixed> $expected
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

        static::assertEquals($expected, $parsed->toArray());
    }

    /**
     * @return iterable<array-key, array{config: array{array{and_logic: string, field: string, tokenize: int, ranking: int|float}}, term: string, expected: array<string, mixed>}>
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
                    self::exactAnalyzed('name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo', 1),
                    self::match('name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo', 0.8, 'AUTO:3,8', 'or', 5),
                    self::prefix('name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo', 0.4),
                ], 1000),
                self::nested('tags', self::disMax([
                    self::exactAnalyzed('tags.name.search', 'foo', 1),
                    self::match('tags.name.search', 'foo', 0.8, 'AUTO:3,8', 'or', 5),
                    self::prefix('tags.name.search', 'foo', 0.4),
                ], 500)),
            ]),
        ];

        yield 'Test single term with numeric field' => [
            'config' => [
                self::config(field: 'restockTime', ranking: 1000),
            ],
            'term' => '2023',
            'expected' => self::term('restockTime', 2023, 1000),
        ];

        yield 'Test multiple fields with terms' => [
            'config' => [
                self::config(field: 'name', ranking: 1000),
                self::config(field: 'ean', ranking: 2000),
                self::config(field: 'restockTime', ranking: 1500),
                self::config(field: 'tags.name', ranking: 500),
            ],
            'term' => 'foo 2023',
            'expected' => self::disMax([
                self::bool([
                    self::bool([
                        self::disMax([
                            self::exactAnalyzed('name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo', 1),
                            self::match('name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo', 0.8, 'AUTO:3,8', 'and', 5),
                            self::prefix('name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo', 0.4),
                        ], 1000),
                        self::disMax([
                            self::exactAnalyzed('ean.search', 'foo', 1),
                            self::match('ean.search', 'foo', 0.8, 'AUTO:3,8', 'and', 5),
                            self::prefix('ean.search', 'foo', 0.4),
                        ], 2000),
                        self::nested('tags', self::disMax([
                            self::exactAnalyzed('tags.name.search', 'foo', 1),
                            self::match('tags.name.search', 'foo', 0.8, 'AUTO:3,8', 'and', 5),
                            self::prefix('tags.name.search', 'foo', 0.4),
                        ], 500)),
                    ]),
                    self::bool([
                        self::disMax([
                            self::exactAnalyzed('name.' . Defaults::LANGUAGE_SYSTEM . '.search', '2023', 1),
                            self::match('name.' . Defaults::LANGUAGE_SYSTEM . '.search', '2023', 0.8, 0, 'and', 10),
                            self::prefix('name.' . Defaults::LANGUAGE_SYSTEM . '.search', '2023', 0.4),
                        ], 1000),
                        self::disMax([
                            self::exactAnalyzed('ean.search', '2023', 1),
                            self::match('ean.search', '2023', 0.8, 0, 'and', 10),
                            self::prefix('ean.search', '2023', 0.4),
                        ], 2000),
                        self::term('restockTime', 2023, 1500),
                        self::nested('tags', self::disMax([
                            self::exactAnalyzed('tags.name.search', '2023', 1),
                            self::match('tags.name.search', '2023', 0.8, 0, 'and', 10),
                            self::prefix('tags.name.search', '2023', 0.4),
                        ], 500)),
                    ]),
                ], BoolQuery::MUST),
                self::bool([
                    self::disMax([
                        self::must('name.' . Defaults::LANGUAGE_SYSTEM, ['foo', '2023'], 1),
                        self::match('name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo 2023', 0.8, 0, 'and', 10),
                        self::matchPhrasePrefix('name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo 2023', 0.6, 3, 10),
                    ], 1000),
                    self::disMax([
                        self::must('ean', ['foo', '2023'], 1),
                        self::match('ean.search', 'foo 2023', 0.8, 0, 'and', 10),
                        self::matchPhrasePrefix('ean.search', 'foo 2023', 0.6, 3, 10),
                    ], 2000),
                    self::nested('tags', self::disMax([
                        self::must('tags.name', ['foo', '2023'], 1),
                        self::match('tags.name.search', 'foo 2023', 0.8, 0, 'and', 10),
                        self::matchPhrasePrefix('tags.name.search', 'foo 2023', 0.6, 3, 10),
                    ], 500)),
                ]),
            ]),
        ];

        yield 'Test multiple fields with all numeric terms' => [
            'config' => [
                self::config(field: 'height', ranking: 2000),
                self::config(field: 'restockTime', ranking: 1500),
            ],
            'term' => 'foo 2023 2024',
            'expected' => self::bool([
                self::bool([
                    self::term('height', 2023.0, 2000),
                    self::term('restockTime', 2023, 1500),
                ]),
                self::bool([
                    self::term('height', 2024.0, 2000),
                    self::term('restockTime', 2024, 1500),
                ]),
            ], BoolQuery::MUST),
        ];

        yield 'Test multiple custom fields with terms' => [
            'config' => [
                self::config(field: 'customFields.evolvesText', ranking: 500),
                self::config(field: 'customFields.evolvesInt', ranking: 400),
                self::config(field: 'customFields.evolvesFloat', ranking: 500),
                self::config(field: 'categories.childCount', ranking: 500),
            ],
            'term' => 'foo 2023',
            'expected' => self::disMax([
                self::bool([
                    self::disMax([
                        self::exactAnalyzed($prefix . 'evolvesText.search', 'foo', 1),
                        self::match($prefix . 'evolvesText.search', 'foo', 0.8, 'AUTO:3,8', 'and', 5),
                        self::prefix($prefix . 'evolvesText.search', 'foo', 0.4),
                    ], 500),
                    self::bool([
                        self::disMax([
                            self::exactAnalyzed($prefix . 'evolvesText.search', '2023', 1),
                            self::match($prefix . 'evolvesText.search', '2023', 0.8, 0, 'and', 10),
                            self::prefix($prefix . 'evolvesText.search', '2023', 0.4),
                        ], 500),
                        self::term($prefix . 'evolvesInt', 2023, 400),
                        self::term($prefix . 'evolvesFloat', 2023.0, 500),
                        self::nested('categories', self::term('categories.childCount', 2023, 500)),
                    ]),
                ], BoolQuery::MUST),
                self::disMax([
                    self::must($prefix . 'evolvesText', ['foo', '2023'], 1),
                    self::match($prefix . 'evolvesText.search', 'foo 2023', 0.8, 0, 'and', 10),
                    self::matchPhrasePrefix($prefix . 'evolvesText.search', 'foo 2023', 0.6, 3, 10),
                ], 500),
            ]),
        ];
    }

    /**
     * @return iterable<array-key, array{config: array{array{and_logic: string, field: string, tokenize: int, ranking: int|float}}, term: string, expected: array<string, mixed>}>
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
                    self::exactAnalyzed('name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo', 1),
                    self::match('name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo', 0.8, 'AUTO:3,8', 'or', 5),
                    self::prefix('name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo', 0.4),
                ], 1000),
                self::nested('tags', self::disMax([
                    self::exactAnalyzed('tags.name.search', 'foo', 1),
                    self::match('tags.name.search', 'foo', 0.8, 'AUTO:3,8', 'or', 5),
                    self::prefix('tags.name.search', 'foo', 0.4),
                ], 500)),
                self::nested('categories', self::disMax([
                    self::disMax([
                        self::exactAnalyzed('categories.name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo', 1),
                        self::match('categories.name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo', 0.8, 'AUTO:3,8', 'or', 5),
                        self::prefix('categories.name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo', 0.4),
                    ], 200),
                    self::disMax([
                        self::exactAnalyzed('categories.name.' . self::SECOND_LANGUAGE_ID . '.search', 'foo', 1),
                        self::match('categories.name.' . self::SECOND_LANGUAGE_ID . '.search', 'foo', 0.8, 'AUTO:3,8', 'or', 5),
                        self::prefix('categories.name.' . self::SECOND_LANGUAGE_ID . '.search', 'foo', 0.4),
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
            'expected' => self::disMax([
                self::bool([
                    self::bool([
                        self::disMax([
                            self::exactAnalyzed('name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo', 1),
                            self::match('name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo', 0.8, 'AUTO:3,8', 'and', 5),
                            self::prefix('name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo', 0.4),
                        ], 1000),
                        self::disMax([
                            self::exactAnalyzed('ean.search', 'foo', 1),
                            self::match('ean.search', 'foo', 0.8, 'AUTO:3,8', 'and', 5),
                            self::prefix('ean.search', 'foo', 0.4),
                        ], 2000),
                        self::nested('tags', self::disMax([
                            self::exactAnalyzed('tags.name.search', 'foo', 1),
                            self::match('tags.name.search', 'foo', 0.8, 'AUTO:3,8', 'and', 5),
                            self::prefix('tags.name.search', 'foo', 0.4),
                        ], 500)),
                    ]),
                    self::bool([
                        self::disMax([
                            self::exactAnalyzed('name.' . Defaults::LANGUAGE_SYSTEM . '.search', '2023', 1),
                            self::match('name.' . Defaults::LANGUAGE_SYSTEM . '.search', '2023', 0.8, 0, 'and', 10),
                            self::prefix('name.' . Defaults::LANGUAGE_SYSTEM . '.search', '2023', 0.4),
                        ], 1000),
                        self::disMax([
                            self::exactAnalyzed('ean.search', '2023', 1),
                            self::match('ean.search', '2023', 0.8, 0, 'and', 10),
                            self::prefix('ean.search', '2023', 0.4),
                        ], 2000),
                        self::term('restockTime', 2023, 1500),
                        self::nested('tags', self::disMax([
                            self::exactAnalyzed('tags.name.search', '2023', 1),
                            self::match('tags.name.search', '2023', 0.8, 0, 'and', 10),
                            self::prefix('tags.name.search', '2023', 0.4),
                        ], 500)),
                    ]),
                ], BoolQuery::MUST),
                self::bool([
                    self::disMax([
                        self::must('name.' . Defaults::LANGUAGE_SYSTEM, ['foo', '2023'], 1),
                        self::match('name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo 2023', 0.8, 0, 'and', 10),
                        self::matchPhrasePrefix('name.' . Defaults::LANGUAGE_SYSTEM . '.search', 'foo 2023', 0.6, 3, 10),
                    ], 1000),
                    self::disMax([
                        self::must('ean', ['foo', '2023'], 1),
                        self::match('ean.search', 'foo 2023', 0.8, 0, 'and', 10),
                        self::matchPhrasePrefix('ean.search', 'foo 2023', 0.6, 3, 10),
                    ], 2000),
                    self::nested('tags', self::disMax([
                        self::must('tags.name', ['foo', '2023'], 1),
                        self::match('tags.name.search', 'foo 2023', 0.8, 0, 'and', 10),
                        self::matchPhrasePrefix('tags.name.search', 'foo 2023', 0.6, 3, 10),
                    ], 500)),
                ]),
            ]),
        ];

        yield 'Test multiple custom fields with terms' => [
            'config' => [
                self::config(field: 'customFields.evolvesText', ranking: 500),
                self::config(field: 'customFields.evolvesInt', ranking: 400),
                self::config(field: 'customFields.evolvesFloat', ranking: 500),
                self::config(field: 'categories.childCount', ranking: 500),
            ],
            'term' => 'foo 2023',
            'expected' => self::disMax([
                self::bool([
                    self::disMax([
                        self::disMax([
                            self::exactAnalyzed($prefixCfLang1 . 'evolvesText.search', 'foo', 1),
                            self::match($prefixCfLang1 . 'evolvesText.search', 'foo', 0.8, 'AUTO:3,8', 'and', 5),
                            self::prefix($prefixCfLang1 . 'evolvesText.search', 'foo', 0.4),
                        ], 500),
                        self::disMax([
                            self::exactAnalyzed($prefixCfLang2 . 'evolvesText.search', 'foo', 1),
                            self::match($prefixCfLang2 . 'evolvesText.search', 'foo', 0.8, 'AUTO:3,8', 'and', 5),
                            self::prefix($prefixCfLang2 . 'evolvesText.search', 'foo', 0.4),
                        ], 400),
                    ]),
                    self::bool([
                        self::disMax([
                            self::disMax([
                                self::exactAnalyzed($prefixCfLang1 . 'evolvesText.search', '2023', 1),
                                self::match($prefixCfLang1 . 'evolvesText.search', '2023', 0.8, 0, 'and', 10),
                                self::prefix($prefixCfLang1 . 'evolvesText.search', '2023', 0.4),
                            ], 500),
                            self::disMax([
                                self::exactAnalyzed($prefixCfLang2 . 'evolvesText.search', '2023', 1),
                                self::match($prefixCfLang2 . 'evolvesText.search', '2023', 0.8, 0, 'and', 10),
                                self::prefix($prefixCfLang2 . 'evolvesText.search', '2023', 0.4),
                            ], 400),
                        ]),
                        self::disMax([
                            self::term($prefixCfLang1 . 'evolvesInt', 2023, 400),
                            self::term($prefixCfLang2 . 'evolvesInt', 2023, 320),
                        ]),
                        self::disMax([
                            self::term($prefixCfLang1 . 'evolvesFloat', 2023, 500),
                            self::term($prefixCfLang2 . 'evolvesFloat', 2023, 400),
                        ]),
                        self::nested('categories', self::term('categories.childCount', 2023, 500)),
                    ]),
                ], BoolQuery::MUST),
                self::disMax([
                    self::disMax([
                        self::must($prefixCfLang1 . 'evolvesText', ['foo', '2023'], 1),
                        self::match($prefixCfLang1 . 'evolvesText.search', 'foo 2023', 0.8, 0, 'and', 10),
                        self::matchPhrasePrefix($prefixCfLang1 . 'evolvesText.search', 'foo 2023', 0.6, 3, 10),
                    ], 500),
                    self::disMax([
                        self::must($prefixCfLang2 . 'evolvesText', ['foo', '2023'], 1),
                        self::match($prefixCfLang2 . 'evolvesText.search', 'foo 2023', 0.8, 0, 'and', 10),
                        self::matchPhrasePrefix($prefixCfLang2 . 'evolvesText.search', 'foo 2023', 0.6, 3, 10),
                    ], 400),
                ]),
            ]),
        ];
    }

    public function testTieBreakerOnTokenizedVsOriginalTermQuery(): void
    {
        $builder = $this->getBuilder([
            self::config(field: 'name', ranking: 1000, tokenize: true, and: true),
            self::config(field: 'ean', ranking: 2000, tokenize: true, and: true),
        ]);

        $criteria = new Criteria();
        $criteria->setTerm('foo 2023');

        $parsed = $builder->build($criteria, Context::createDefaultContext());
        $queryArray = $parsed->toArray();

        static::assertArrayHasKey('dis_max', $queryArray);
        static::assertSame(0.2, $queryArray['dis_max']['tie_breaker']);
        static::assertCount(2, $queryArray['dis_max']['queries']);

        $tokensQuery = $queryArray['dis_max']['queries'][0];
        static::assertArrayHasKey('bool', $tokensQuery);

        $originalTermQuery = $queryArray['dis_max']['queries'][1];
        static::assertArrayHasKey('bool', $originalTermQuery);

        $fieldQueries = $originalTermQuery['bool']['should'];
        foreach ($fieldQueries as $fieldQuery) {
            $disMax = $fieldQuery['dis_max'] ?? null;
            static::assertNotNull($disMax);
            static::assertSame(0.2, $disMax['tie_breaker']);
        }
    }

    public function testTranslatedSingleTokenExactMatchUsesExactSubfieldWhenConfigured(): void
    {
        $builder = $this->getBuilder([
            self::config(field: 'name', ranking: 1000, useExactSubfield: true),
        ]);

        $criteria = new Criteria();
        $criteria->setTerm('foo');

        $parsed = $builder->build($criteria, Context::createDefaultContext());

        static::assertStringContainsString(
            '"name.' . Defaults::LANGUAGE_SYSTEM . '.exact"',
            json_encode($parsed->toArray(), \JSON_THROW_ON_ERROR),
        );
    }

    public function testTranslatedMultiTokenExactMatchUsesKeywordFieldWhenConfigured(): void
    {
        $builder = $this->getBuilder([
            self::config(field: 'name', ranking: 1000, useExactSubfield: true),
        ]);

        $criteria = new Criteria();
        $criteria->setTerm('foo bar');

        $parsed = $builder->build($criteria, Context::createDefaultContext());

        $queryArray = $parsed->toArray();
        $originalTermQuery = $queryArray['dis_max']['queries'][1] ?? null;

        static::assertNotNull($originalTermQuery);
        static::assertStringContainsString(
            '"name.' . Defaults::LANGUAGE_SYSTEM . '"',
            json_encode($originalTermQuery, \JSON_THROW_ON_ERROR),
        );
        static::assertStringNotContainsString(
            '"name.' . Defaults::LANGUAGE_SYSTEM . '.exact"',
            json_encode($originalTermQuery, \JSON_THROW_ON_ERROR),
        );
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

    /**
     * @param array{array{and_logic: string, field: string, tokenize: int, ranking: int|float}}|null $config
     */
    private function getBuilder(?array $config): ProductSearchQueryBuilder
    {
        $configLoader = $this->createMock(SearchConfigLoader::class);
        $configLoader->method('load')->willReturn($config ?? []);

        $tokenFilter = $this->createMock(AbstractTokenFilter::class);
        $tokenFilter->method('filter')->willReturnArgument(0);

        return new ProductSearchQueryBuilder(
            $this->getDefinition(),
            $tokenFilter,
            new Tokenizer(2),
            $configLoader,
            $this->tokenQueryBuilder
        );
    }

    /**
     * @return array{and_logic: string, field: string, tokenize: int, ranking: float, use_exact_subfield: int}
     */
    private static function config(string $field, float $ranking, bool $tokenize = false, bool $and = true, bool $useExactSubfield = false): array
    {
        return [
            'and_logic' => $and ? '1' : '0',
            'field' => $field,
            'tokenize' => $tokenize ? 1 : 0,
            'ranking' => $ranking,
            'use_exact_subfield' => $useExactSubfield ? 1 : 0,
        ];
    }

    /**
     * @return array{term: array<string, array{value: string|int|float, boost: float}>}
     */
    private static function term(string $field, string|int|float $query, float $boost): array
    {
        return [
            'term' => [
                $field => [
                    'boost' => $boost,
                    'value' => $query,
                ],
            ],
        ];
    }

    /**
     * @return array{match: array<string, array{query: string|int|float, boost: float, fuzziness: int, operator: string}>}
     */
    private static function exactAnalyzed(string $field, string|int|float $query, float $boost): array
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
     * @return array{match: array<string, array{query: string|int|float, boost: float, operator: string, fuzzy_transpositions: bool, prefix_length: int, fuzziness?: int|string, max_expansions?: int}>}
     */
    private static function match(string $field, string|int|float $query, int|float $boost, int|string|null $fuzziness = null, string $operator = 'and', ?int $maxExpansions = null): array
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
    private static function bool(array $queries, string $operator = BoolQuery::SHOULD): array
    {
        return [
            'bool' => [
                $operator => $queries,
            ],
        ];
    }

    /**
     * @param array<string> $tokens
     *
     * @return array{bool: array{must: array<array{term: array<string, string>}>, boost: float|int}}
     */
    private static function must(string $field, array $tokens, int|float $boost = 1): array
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
     * @return array{match_bool_prefix: array<string, array{query: string|int|float, boost: float}>}
     */
    private static function prefix(string $field, string|int|float $query, float $boost): array
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

    public function getCustomField(string $attributeName): Field
    {
        return $this->config[$attributeName];
    }
}
