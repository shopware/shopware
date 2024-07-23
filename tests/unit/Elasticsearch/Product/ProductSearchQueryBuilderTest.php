<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Product;

use Doctrine\DBAL\Connection;
use OpenSearchDSL\BuilderInterface;
use OpenSearchDSL\Query\Compound\BoolQuery;
use OpenSearchDSL\Query\FullText\MatchQuery;
use OpenSearchDSL\Query\FullText\MultiMatchQuery;
use OpenSearchDSL\Query\Joining\NestedQuery;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationDefinition;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductCategory\ProductCategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturerTranslation\ProductManufacturerTranslationDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Filter\AbstractTokenFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Filter\TokenFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Tokenizer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;
use Shopware\Elasticsearch\Product\ProductSearchQueryBuilder;
use Shopware\Tests\Unit\Common\Stubs\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 *
 * @covers \Shopware\Elasticsearch\Product\ProductSearchQueryBuilder
 */
class ProductSearchQueryBuilderTest extends TestCase
{
    public function testDecoration(): void
    {
        $builder = new ProductSearchQueryBuilder(
            $this->createMock(Connection::class),
            new EntityDefinitionQueryHelper(),
            $this->getDefinition(),
            $this->createMock(TokenFilter::class),
            new Tokenizer(2),
            $this->createMock(ElasticsearchHelper::class)
        );

        static::expectException(DecorationPatternException::class);
        $builder->getDecorated();
    }

    public function testBuildQueryAndSearch(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchAllAssociative')
            ->willReturn([
                ['and_logic' => '1', 'field' => 'name', 'tokenize' => 1, 'ranking' => 500],
                ['and_logic' => '1', 'field' => 'description', 'tokenize' => 0, 'ranking' => 500],
            ]);

        $tokenFilter = $this->createMock(AbstractTokenFilter::class);
        $tokenFilter
            ->method('filter')
            ->willReturnArgument(0);

        $helper = new EntityDefinitionQueryHelper();

        $elasticsearchQueryHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchQueryHelper->method('enabledMultilingualIndex')->willReturn(Feature::isActive('ES_MULTILINGUAL_INDEX'));

        $builder = new ProductSearchQueryBuilder(
            $connection,
            $helper,
            $this->getDefinition(),
            $tokenFilter,
            new Tokenizer(2),
            $elasticsearchQueryHelper
        );

        $criteria = new Criteria();
        $criteria->setTerm('foo bla');
        $queries = $builder->build($criteria, Context::createDefaultContext());

        static::assertEmpty($queries->getQueries(BoolQuery::SHOULD));

        /** @var BoolQuery[] $tokenQueries */
        $tokenQueries = array_values($queries->getQueries(BoolQuery::MUST));

        static::assertCount(3, $tokenQueries, 'Expected 3 token queries due to token searches');

        $nameQueries = array_map(fn (BuilderInterface $query) => $query->toArray(), array_values($tokenQueries[0]->getQueries(BoolQuery::SHOULD)));

        if (Feature::isActive('ES_MULTILINGUAL_INDEX')) {
            static::assertCount(6, $nameQueries);

            $expectedQueries = [
                [
                    'multi_match' => [
                        'query' => 'foo',
                        'fields' => [
                            'name.2fbb5fe2e29a4d70aa5854ce7ce3e20b.search',
                        ],
                        'type' => 'best_fields',
                        'fuzziness' => 0,
                        'boost' => 2500,
                    ],
                ],
                [
                    'multi_match' => [
                        'query' => 'foo',
                        'fields' => [
                            'name.2fbb5fe2e29a4d70aa5854ce7ce3e20b.search',
                        ],
                        'type' => 'phrase_prefix',
                        'slop' => 5,
                        'boost' => 500,
                    ],
                ],
                [
                    'multi_match' => [
                        'query' => 'foo',
                        'fields' => [
                            'name.2fbb5fe2e29a4d70aa5854ce7ce3e20b.search',
                        ],
                        'type' => 'best_fields',
                        'fuzziness' => 'auto',
                        'boost' => 1500,
                    ],
                ],
                [
                    'multi_match' => [
                        'query' => 'foo',
                        'fields' => [
                            'name.2fbb5fe2e29a4d70aa5854ce7ce3e20b.ngram',
                        ],
                        'type' => 'phrase',
                        'boost' => 500,
                    ],
                ],
                [
                    'multi_match' => [
                        'query' => 'foo',
                        'fields' => [
                            'description.2fbb5fe2e29a4d70aa5854ce7ce3e20b.search',
                        ],
                        'type' => 'best_fields',
                        'fuzziness' => 0,
                        'boost' => 2500,
                    ],
                ],
                [
                    'multi_match' => [
                        'query' => 'foo',
                        'fields' => [
                            'description.2fbb5fe2e29a4d70aa5854ce7ce3e20b.search',
                        ],
                        'type' => 'phrase_prefix',
                        'slop' => 5,
                        'boost' => 500,
                    ],
                ],
            ];

            static::assertSame($expectedQueries, $nameQueries);
        } else {
            static::assertCount(8, $nameQueries);

            $expectedQueries = [
                ['match' => [
                    'name.search' => [
                        'query' => 'foo',
                        'boost' => 2500,
                    ],
                ],
                ],
                [
                    'match_phrase_prefix' => [
                        'name.search' => [
                            'query' => 'foo',
                            'boost' => 500,
                            'slop' => 5,
                        ],
                    ],
                ],
                [
                    'wildcard' => [
                        'name.search' => [
                            'value' => '*foo*',
                        ],
                    ],
                ],
                [
                    'match' => [
                        'name.search' => [
                            'query' => 'foo',
                            'fuzziness' => 'auto',
                            'boost' => 1500,
                        ],
                    ],
                ],
                [
                    'match' => [
                        'name.ngram' => [
                            'query' => 'foo',
                        ],
                    ],
                ],
                [
                    'match' => [
                        'description.search' => [
                            'query' => 'foo',
                            'boost' => 2500,
                        ],
                    ],
                ],
                [
                    'match_phrase_prefix' => [
                        'description.search' => [
                            'query' => 'foo',
                            'boost' => 500,
                            'slop' => 5,
                        ],
                    ],
                ],
                [
                    'wildcard' => [
                        'description.search' => [
                            'value' => '*foo*',
                        ],
                    ],
                ],
            ];

            static::assertSame($expectedQueries, $nameQueries);
        }
    }

    public function testNestedQueries(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchAllAssociative')
            ->willReturn([
                ['and_logic' => '1', 'field' => 'categories.name', 'tokenize' => 1, 'ranking' => 500],
            ]);

        $tokenFilter = $this->createMock(AbstractTokenFilter::class);
        $tokenFilter
            ->method('filter')
            ->willReturnArgument(0);

        $elasticsearchQueryHelper = $this->createMock(ElasticsearchHelper::class);
        $elasticsearchQueryHelper->method('enabledMultilingualIndex')->willReturn(Feature::isActive('ES_MULTILINGUAL_INDEX'));

        $builder = new ProductSearchQueryBuilder(
            $connection,
            new EntityDefinitionQueryHelper(),
            $this->getDefinition(),
            $tokenFilter,
            new Tokenizer(2),
            $elasticsearchQueryHelper
        );

        $criteria = new Criteria();
        $criteria->setTerm('foo bla');
        $queries = $builder->build($criteria, Context::createDefaultContext());

        /** @var BoolQuery $boolQuery */
        $boolQuery = array_values($queries->getQueries(BoolQuery::MUST))[0];

        $esQueries = array_values($boolQuery->getQueries(BoolQuery::SHOULD));

        static::assertNotEmpty($esQueries);

        $first = $esQueries[0];

        static::assertInstanceOf(NestedQuery::class, $first);

        static::assertSame('categories', $first->getPath());

        $query = $first->getQuery();

        if (Feature::isActive('ES_MULTILINGUAL_INDEX')) {
            static::assertInstanceOf(MultiMatchQuery::class, $query);

            static::assertSame(
                [
                    'multi_match' => [
                        'query' => 'foo',
                        'fields' => [
                            'categories.name.2fbb5fe2e29a4d70aa5854ce7ce3e20b.search',
                        ],
                        'type' => 'best_fields',
                        'fuzziness' => 0,
                        'boost' => 2500,
                    ],
                ],
                $query->toArray()
            );
        } else {
            static::assertInstanceOf(MatchQuery::class, $query);

            static::assertSame(
                [
                    'match' => [
                        'categories.name.search' => [
                            'query' => 'foo',
                            'boost' => 2500,
                        ],
                    ],
                ],
                $query->toArray()
            );
        }
    }

    public function testOrSearch(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchAllAssociative')
            ->willReturn([
                ['and_logic' => '0', 'field' => 'name', 'tokenize' => 1, 'ranking' => 500],
                ['and_logic' => '0', 'field' => 'description', 'tokenize' => 0, 'ranking' => 500],
            ]);

        $tokenFilter = $this->createMock(AbstractTokenFilter::class);
        $tokenFilter
            ->method('filter')
            ->willReturnArgument(0);

        $builder = new ProductSearchQueryBuilder(
            $connection,
            new EntityDefinitionQueryHelper(),
            $this->getDefinition(),
            $tokenFilter,
            new Tokenizer(2),
            $this->createMock(ElasticsearchHelper::class)
        );

        $criteria = new Criteria();
        $criteria->setTerm('foo bla');
        $queries = $builder->build($criteria, Context::createDefaultContext());

        static::assertNotEmpty($queries->getQueries(BoolQuery::SHOULD));
        static::assertEmpty($queries->getQueries(BoolQuery::MUST));
    }

    public function getDefinition(): EntityDefinition
    {
        $instanceRegistry = new StaticDefinitionInstanceRegistry(
            [
                ProductDefinition::class,
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
}
