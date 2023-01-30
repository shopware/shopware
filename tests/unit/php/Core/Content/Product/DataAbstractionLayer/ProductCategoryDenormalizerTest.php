<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Statement;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductCategoryDenormalizer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 *
 * @covers \Shopware\Core\Content\Product\DataAbstractionLayer\ProductCategoryDenormalizer
 */
class ProductCategoryDenormalizerTest extends TestCase
{
    private IdsCollection $ids;

    private ProductCategoryDenormalizer $productCategoryDenormalizer;

    private Connection&MockObject $connection;

    private MockObject&QueryBuilder $queryBuilder;

    public function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->connection = static::createMock(Connection::class);
        $this->connection->method('transactional')
            ->willReturnCallback(fn (\Closure $func) => $func($this->connection));
        $this->queryBuilder = static::createMock(QueryBuilder::class);
        $this->productCategoryDenormalizer = new ProductCategoryDenormalizer($this->connection);
    }

    public function testUpdateWithNoIdsWillNotCallConnection(): void
    {
        $this->connection
            ->expects(static::never())
            ->method(static::anything());

        $this->productCategoryDenormalizer->update([], Context::createDefaultContext());
    }

    public function testUpdateWithProductIdsWithNoCategoryAssignmentWillWriteEmptyCategoryTree(): void
    {
        $statement = $this->createMock(Statement::class);
        $statement
            ->method('execute')
            ->with(
                static::logicalAnd(
                    static::arrayHasKey('tree'),
                    static::callback(fn (array $arr) => empty($arr['tree'])),
                )
            );
        $result = $this->createMock(Result::class);
        $result
            ->method('fetchAllAssociative')
            ->willReturn(
                [
                    [
                        'product_id' => Uuid::randomHex(),
                        'ids' => '',
                        'paths' => '',
                    ],
                ]
            );
        $this->queryBuilder
            ->expects(static::atLeast(1))
            ->method('executeQuery')
            ->willReturn($result);
        $this->connection
            ->method('createQueryBuilder')
            ->willReturn($this->queryBuilder);
        $this->connection
            ->expects(static::once())
            ->method('executeStatement')
            ->with(static::stringStartsWith('DELETE'));
        $this->connection
            ->expects(static::atLeast(1))
            ->method('prepare')
            ->with(static::stringStartsWith('UPDATE'))
            ->willReturn($statement);

        $this->productCategoryDenormalizer->update([Uuid::randomHex()], Context::createDefaultContext());
    }

    public function testUpdateWithProductIdsWithExistingSubcategoryAssignmentWillProvideInserts(): void
    {
        $productId = $this->ids->create('product');

        $statement = $this->createMock(Statement::class);
        $statement
            ->expects(static::exactly(1))
            ->method('execute')
            ->with(
                static::containsEqual(Uuid::fromHexToBytes($productId))
            );
        $result = $this->createMock(Result::class);
        $result
            ->method('fetchAllAssociative')
            ->willReturn(
                [
                    [
                        'product_id' => $this->ids->get('product'),
                        'ids' => sprintf('%s', $this->ids->create('level-2')),
                        'paths' => sprintf('%s|%s|%s', $this->ids->create('root-cat'), $this->ids->create('level-1'), $this->ids->get('level-2')),
                    ],
                ]
            );
        $this->queryBuilder
            ->method('executeQuery')
            ->willReturn($result);
        $this->connection
            ->method('createQueryBuilder')
            ->willReturn($this->queryBuilder);
        $this->connection
            ->expects(static::exactly(2))
            ->method('executeStatement')
            ->withConsecutive(
                [
                    static::stringContains('DELETE'),
                    static::logicalAnd(
                        static::arrayHasKey('ids'),
                        static::callback(fn ($bindings) => \in_array(Uuid::fromHexToBytes($this->ids->get('product')), $bindings['ids'], true))
                    ),
                    static::anything(),
                ],
                [
                    static::matchesRegularExpression('/INSERT.*VALUES(\s+\(,,,\),?\s*){3}/'),
                    static::anything(),
                    static::anything(),
                ],
            );
        $this->connection
            ->method('prepare')
            ->with(static::stringStartsWith('UPDATE'))
            ->willReturn($statement);

        $this->productCategoryDenormalizer->update([$this->ids->get('product')], Context::createDefaultContext());
    }

    public function testUpdateWithProductIdsAndExistingRootCategoryAssignmentWillProvideOneInsert(): void
    {
        $result = $this->createMock(Result::class);
        $result
            ->method('fetchAllAssociative')
            ->willReturn(
                [
                    [
                        'product_id' => $this->ids->create('product'),
                        'ids' => sprintf('%s', $this->ids->create('root-cat')),
                        'paths' => sprintf('%s', $this->ids->get('root-cat')),
                    ],
                ]
            );
        $statement = $this->createMock(Statement::class);
        $statement
            ->expects(static::atLeast(1))
            ->method('execute')
            ->with(
                static::logicalAnd(
                    static::arrayHasKey('id'),
                    static::callback(fn ($bindings) => Uuid::fromHexToBytes($this->ids->get('product')) === $bindings['id'])
                )
            );
        $this->queryBuilder
            ->method('executeQuery')
            ->willReturn($result);
        $this->connection
            ->method('createQueryBuilder')
            ->willReturn($this->queryBuilder);
        $this->connection
            ->expects(static::exactly(2))
            ->method('executeStatement')
            ->withConsecutive(
                [static::stringContains('DELETE'), static::anything(), static::anything()],
                [
                    static::matchesRegularExpression('/INSERT.*VALUES\s+(\(,,,\)\s*);/'),
                    static::anything(),
                    static::anything(),
                ],
            );
        $this->connection
            ->method('prepare')
            ->with(static::stringStartsWith('UPDATE'))
            ->willReturn($statement);

        $this->productCategoryDenormalizer->update([$this->ids->get('product')], Context::createDefaultContext());
    }

    public function testUpdateWithProductIdsWithCategoryAssignmentWillWriteCategoryTreeWithValidJSON(): void
    {
        $validJson = function ($bindings) {
            json_decode((string) $bindings['tree'], true);

            return json_last_error() === \JSON_ERROR_NONE;
        };
        $statement = $this->createMock(Statement::class);
        $statement
            ->expects(static::exactly(1))
            ->method('execute')
            ->with(
                static::logicalAnd(
                    static::arrayHasKey('tree'),
                    static::callback($validJson)
                )
            );
        $result = $this->createMock(Result::class);
        $result
            ->method('fetchAllAssociative')
            ->willReturn(
                [
                    [
                        'product_id' => $this->ids->create('product'),
                        'ids' => sprintf('%s', $this->ids->create('level-2')),
                        'paths' => sprintf('%s|%s|%s', $this->ids->create('root-cat'), $this->ids->create('level-1'), $this->ids->get('level-2')),
                    ],
                ]
            );
        $this->queryBuilder
            ->method('executeQuery')
            ->willReturn($result);
        $this->connection
            ->method('createQueryBuilder')
            ->willReturn($this->queryBuilder);
        $this->connection
            ->method('prepare')
            ->with(static::stringStartsWith('UPDATE'))
            ->willReturn($statement);

        $this->productCategoryDenormalizer->update([$this->ids->get('product')], Context::createDefaultContext());
    }
}
