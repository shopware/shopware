<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDO\Exception as DriverException;
use Doctrine\DBAL\Exception\DeadlockException;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Statement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductCategoryDenormalizer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ProductCategoryDenormalizer::class)]
class ProductCategoryDenormalizerTest extends TestCase
{
    /**
     * A parent write rewrites the tree of every variant that inherits its categories.
     * Other connections must never see those variants without category rows, or
     * category based product streams drop the variants that are written meanwhile.
     */
    public function testRewritesTheCategoryTreeInOneTransaction(): void
    {
        $parentId = Uuid::randomHex();
        $categoryId = Uuid::randomHex();

        $writes = $this->captureWrites(
            [
                ['product_id' => $parentId, 'paths' => '|' . Uuid::randomHex() . '|', 'ids' => $categoryId],
                ['product_id' => Uuid::randomHex(), 'paths' => '|' . Uuid::randomHex() . '|', 'ids' => $categoryId],
                ['product_id' => Uuid::randomHex(), 'paths' => '|' . Uuid::randomHex() . '|', 'ids' => $categoryId],
            ],
            static fn (ProductCategoryDenormalizer $denormalizer) => $denormalizer->update([$parentId], Context::createDefaultContext())
        );

        static::assertNotSame([], $writes);
        static::assertNotContains(null, $writes, 'The category tree was written outside of a transaction.');
        static::assertCount(1, array_unique($writes), 'The category tree was written in more than one transaction.');
    }

    public function testReplaysAllCategoryWritesWhenTransactionIsRetried(): void
    {
        $productId = Uuid::randomHex();
        $writes = $this->captureWrites(
            [['product_id' => $productId, 'paths' => '', 'ids' => Uuid::randomHex()]],
            static fn (ProductCategoryDenormalizer $denormalizer) => $denormalizer->update([$productId], Context::createDefaultContext()),
            retryFirstCommit: true
        );

        static::assertSame([1, 1, 1, 2, 2, 2], $writes, 'The retry must replay the delete, product update and mapping insert.');
    }

    /**
     * @param list<array{product_id: string, paths: string, ids: string}> $mapping category mapping the read side returns per product
     * @param \Closure(ProductCategoryDenormalizer): void $action
     *
     * @return list<int|null> for every write, the number of the outermost transaction it ran in, or null outside of one
     */
    private function captureWrites(array $mapping, \Closure $action, bool $retryFirstCommit = false): array
    {
        $recorder = new TransactionRecorder($retryFirstCommit);

        $result = static::createStub(Result::class);
        $result->method('fetchAllAssociative')->willReturn($mapping);

        $statement = static::createStub(Statement::class);
        $statement->method('executeStatement')->willReturnCallback($recorder->write(...));

        $connection = $this->createMock(Connection::class);
        $connection->method('createQueryBuilder')->willReturnCallback(static fn (): QueryBuilder => new QueryBuilder($connection));
        $connection->method('getDatabasePlatform')->willReturn(new MySQLPlatform());
        $connection->method('executeQuery')->willReturn($result);
        $connection->method('prepare')->willReturn($statement);
        $connection->method('executeStatement')->willReturnCallback($recorder->write(...));
        $connection->method('getTransactionNestingLevel')->willReturnCallback(static fn (): int => $recorder->depth);

        // the double has to really run the closure, otherwise no write is ever reached and the test passes vacuously
        $connection->expects($this->atLeastOnce())
            ->method('transactional')
            ->willReturnCallback(static fn (\Closure $closure): mixed => $recorder->transactional($closure, $connection));

        $action(new ProductCategoryDenormalizer($connection));

        return $recorder->writes;
    }
}

/**
 * @internal
 */
class TransactionRecorder
{
    public int $depth = 0;

    /**
     * @var list<int|null>
     */
    public array $writes = [];

    private int $outermost = 0;

    public function __construct(private readonly bool $retryFirstCommit = false)
    {
    }

    public function transactional(\Closure $closure, Connection $connection): mixed
    {
        if ($this->depth === 0) {
            ++$this->outermost;
        }

        ++$this->depth;

        try {
            $result = $closure($connection);

            if ($this->retryFirstCommit && $this->depth === 1 && $this->outermost === 1) {
                throw new DeadlockException(new DriverException('Deadlock on commit'), null);
            }

            return $result;
        } finally {
            --$this->depth;
        }
    }

    public function write(): int
    {
        $this->writes[] = $this->depth > 0 ? $this->outermost : null;

        return 1;
    }
}
