<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Product;

use Doctrine\DBAL\Connection;
use OpenSearchDSL\BuilderInterface;
use OpenSearchDSL\Query\Compound\BoolQuery;
use OpenSearchDSL\Query\FullText\MatchQuery;
use OpenSearchDSL\Query\Joining\NestedQuery;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Filter\AbstractTokenFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Tokenizer;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Elasticsearch\Product\ProductSearchQueryBuilder;

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
            new Tokenizer(2),
            $this->createMock(AbstractTokenFilter::class)
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

        $builder = new ProductSearchQueryBuilder(
            $connection,
            new Tokenizer(2),
            $tokenFilter
        );

        $criteria = new Criteria();
        $criteria->setTerm('foo bla');
        $queries = $builder->build($criteria, Context::createDefaultContext());

        static::assertEmpty($queries->getQueries(BoolQuery::SHOULD));

        /** @var BoolQuery[] $tokenQueries */
        $tokenQueries = array_values($queries->getQueries(BoolQuery::MUST));

        static::assertCount(2, $tokenQueries, 'Expected 2 token queries due to token searches');

        $nameQueries = array_map(fn (BuilderInterface $query) => $query->toArray(), array_values($tokenQueries[0]->getQueries(BoolQuery::SHOULD)));

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

        $builder = new ProductSearchQueryBuilder(
            $connection,
            new Tokenizer(2),
            $tokenFilter
        );

        $criteria = new Criteria();
        $criteria->setTerm('foo bla');
        $queries = $builder->build($criteria, Context::createDefaultContext());

        $boolQuery = array_values($queries->getQueries(BoolQuery::MUST))[0];

        $esQueries = array_values($boolQuery->getQueries(BoolQuery::SHOULD));

        static::assertNotEmpty($esQueries);

        $first = $esQueries[0];

        static::assertInstanceOf(NestedQuery::class, $first);

        static::assertSame('categories', $first->getPath());

        $query = $first->getQuery();

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
            new Tokenizer(2),
            $tokenFilter
        );

        $criteria = new Criteria();
        $criteria->setTerm('foo bla');
        $queries = $builder->build($criteria, Context::createDefaultContext());

        static::assertNotEmpty($queries->getQueries(BoolQuery::SHOULD));
        static::assertEmpty($queries->getQueries(BoolQuery::MUST));
    }
}
