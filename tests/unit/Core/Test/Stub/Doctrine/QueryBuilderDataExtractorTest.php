<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Test\Stub\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\Expression\CompositeExpression;
use Doctrine\DBAL\Query\QueryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\Doctrine\QueryBuilderDataExtractor;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(QueryBuilderDataExtractor::class)]
class QueryBuilderDataExtractorTest extends TestCase
{
    private QueryBuilder $queryBuilder;

    protected function setUp(): void
    {
        $this->queryBuilder = new QueryBuilder($this->createMock(Connection::class));
    }

    public function testGetSelect(): void
    {
        $this->queryBuilder->select('column1', 'column2');

        $select = QueryBuilderDataExtractor::getSelect($this->queryBuilder);
        static::assertSame(['column1', 'column2'], $select);
    }

    public function testGetFrom(): void
    {
        $this->queryBuilder->from('table1', 'alias1');
        $this->queryBuilder->from('table2');

        $result = QueryBuilderDataExtractor::getFrom($this->queryBuilder);
        static::assertSame(['table1', 'table2'], $result);
    }

    public function testGetWhere(): void
    {
        $this->queryBuilder->where('c.name=:name')
            ->andWhere('c.active=:active');

        $result = QueryBuilderDataExtractor::getWhere($this->queryBuilder);
        static::assertInstanceOf(CompositeExpression::class, $result);
        static::assertEquals(
            CompositeExpression::and(
                'c.name=:name',
                'c.active=:active',
            ),
            $result
        );
    }

    public function testGetJoin(): void
    {
        $this->queryBuilder
            ->select('p.id', 'p.name')
            ->from('products', 'p')
            ->leftJoin('p', 'reviews', 'r', 'p.id = r.product_id')
        ;

        $result = QueryBuilderDataExtractor::getJoin($this->queryBuilder);
        static::assertSame([
            'p' => [
                [
                    'type' => 'LEFT',
                    'table' => 'reviews',
                    'alias' => 'r',
                    'condition' => 'p.id = r.product_id',
                ],
            ],
        ], $result);
    }
}
