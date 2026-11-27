<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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
    private const PRODUCT_ID = '0aa3b1c9e1b44a43a8a4c0b7f1d2e3f4';

    private const ROOT_CATEGORY_ID = '1bb3b1c9e1b44a43a8a4c0b7f1d2e3f4';

    private const CATEGORY_ID = '2cc3b1c9e1b44a43a8a4c0b7f1d2e3f4';

    public function testRewritesTheCategoryTreeInOneTransaction(): void
    {
        $parentId = Uuid::randomHex();
        $categoryId = Uuid::randomHex();

        $writes = $this->captureWrites(
            [
                ['product_id' => $parentId, 'paths' => '|' . Uuid::randomHex() . '|', 'ids' => $categoryId, 'category_tree' => null],
                ['product_id' => Uuid::randomHex(), 'paths' => '|' . Uuid::randomHex() . '|', 'ids' => $categoryId, 'category_tree' => null],
                ['product_id' => Uuid::randomHex(), 'paths' => '|' . Uuid::randomHex() . '|', 'ids' => $categoryId, 'category_tree' => null],
            ],
            storedRows: [],
            action: static fn (ProductCategoryDenormalizer $denormalizer) => $denormalizer->update([$parentId], Context::createDefaultContext())
        );

        $transactions = array_column($writes, 'transaction');
        static::assertNotSame([], $transactions);
        static::assertNotContains(null, $transactions, 'The category tree was written outside of a transaction.');
        static::assertCount(1, array_unique($transactions), 'The category tree was written in more than one transaction.');
    }

    public function testRemovesTheCategoryFromEveryProductOfAFamilyLargerThanOneBatch(): void
    {
        $productIds = array_map(static fn (): string => Uuid::randomHex(), range(1, 300));

        $writes = $this->captureWrites(
            array_map(static fn (string $productId): array => [
                'product_id' => $productId,
                'paths' => null,
                'ids' => self::ROOT_CATEGORY_ID,
                'category_tree' => '["' . self::ROOT_CATEGORY_ID . '","' . self::CATEGORY_ID . '"]',
            ], $productIds),
            array_merge(...array_map(static fn (string $productId): array => [
                ['product_id' => $productId, 'category_id' => self::ROOT_CATEGORY_ID],
                ['product_id' => $productId, 'category_id' => self::CATEGORY_ID],
            ], $productIds)),
            static fn (ProductCategoryDenormalizer $denormalizer) => $denormalizer->update([$productIds[0]], Context::createDefaultContext())
        );

        $products = array_values(Uuid::fromHexToBytesList($productIds));
        static::assertEqualsCanonicalizing(
            $products,
            self::productsWrittenWith('["' . self::ROOT_CATEGORY_ID . '"]', $products, $writes),
            'Not every product got its new tree.'
        );
        static::assertEqualsCanonicalizing(
            $products,
            self::productsWrittenWith(Uuid::fromHexToBytes(self::CATEGORY_ID), $products, $writes),
            'The removed category was not deleted for every product.'
        );
    }

    public function testWritesEachProductItsOwnTree(): void
    {
        $productId = Uuid::randomHex();
        $otherProductId = Uuid::randomHex();

        $writes = $this->captureWrites(
            [
                ['product_id' => $productId, 'paths' => null, 'ids' => self::ROOT_CATEGORY_ID, 'category_tree' => null],
                ['product_id' => $otherProductId, 'paths' => '|' . self::ROOT_CATEGORY_ID . '|', 'ids' => self::CATEGORY_ID, 'category_tree' => null],
            ],
            storedRows: [],
            action: static fn (ProductCategoryDenormalizer $denormalizer) => $denormalizer->update([$productId, $otherProductId], Context::createDefaultContext())
        );

        $product = Uuid::fromHexToBytes($productId);
        $otherProduct = Uuid::fromHexToBytes($otherProductId);
        static::assertSame(
            [$product],
            self::productsWrittenWith('["' . self::ROOT_CATEGORY_ID . '"]', [$product, $otherProduct], $writes)
        );
        static::assertSame(
            [$otherProduct],
            self::productsWrittenWith('["' . self::ROOT_CATEGORY_ID . '","' . self::CATEGORY_ID . '"]', [$product, $otherProduct], $writes)
        );
    }

    /**
     * @param list<string> $storedCategoryIds
     */
    #[DataProvider('unchangedTreeProvider')]
    public function testWritesNothingWhenTheTreeIsUnchanged(?string $paths, ?string $ids, ?string $storedTree, array $storedCategoryIds): void
    {
        $writes = $this->captureWrites(
            [['product_id' => self::PRODUCT_ID, 'paths' => $paths, 'ids' => $ids, 'category_tree' => $storedTree]],
            array_map(static fn (string $categoryId): array => ['product_id' => self::PRODUCT_ID, 'category_id' => $categoryId], $storedCategoryIds),
            static fn (ProductCategoryDenormalizer $denormalizer) => $denormalizer->update([self::PRODUCT_ID], Context::createDefaultContext())
        );

        static::assertSame([], $writes);
    }

    public static function unchangedTreeProvider(): \Generator
    {
        yield 'tree stored in another order' => [
            'paths' => '|' . self::ROOT_CATEGORY_ID . '|',
            'ids' => self::CATEGORY_ID,
            'storedTree' => '["' . self::CATEGORY_ID . '","' . self::ROOT_CATEGORY_ID . '"]',
            'storedCategoryIds' => [self::CATEGORY_ID, self::ROOT_CATEGORY_ID],
        ];

        yield 'tree stored the way MySQL formats JSON' => [
            'paths' => '|' . self::ROOT_CATEGORY_ID . '|',
            'ids' => self::CATEGORY_ID,
            'storedTree' => '["' . self::ROOT_CATEGORY_ID . '", "' . self::CATEGORY_ID . '"]',
            'storedCategoryIds' => [self::ROOT_CATEGORY_ID, self::CATEGORY_ID],
        ];

        yield 'product without categories and without a stored tree' => [
            'paths' => null,
            'ids' => null,
            'storedTree' => null,
            'storedCategoryIds' => [],
        ];
    }

    /**
     * @param list<array{product_id: string, paths: string|null, ids: string|null, category_tree: string|null}> $mapping
     * @param list<array{product_id: string, category_id: string}> $storedRows
     * @param \Closure(ProductCategoryDenormalizer): void $action
     *
     * @return list<array{transaction: int|null, values: list<mixed>}>
     */
    private function captureWrites(array $mapping, array $storedRows, \Closure $action): array
    {
        $recorder = new TransactionRecorder();

        $result = static::createStub(Result::class);
        $result->method('fetchAllAssociative')->willReturn($mapping);

        $connection = $this->createMock(Connection::class);
        $connection->method('createQueryBuilder')->willReturnCallback(static fn (): QueryBuilder => new QueryBuilder($connection));
        $connection->method('getDatabasePlatform')->willReturn(new MySQLPlatform());
        $connection->method('fetchFirstColumn')->willReturn(array_column($mapping, 'product_id'));
        $connection->method('fetchAllAssociative')->willReturn($storedRows);
        $connection->method('executeQuery')->willReturn($result);
        $connection->method('executeStatement')->willReturnCallback($recorder->write(...));
        $connection->method('getTransactionNestingLevel')->willReturnCallback(static fn (): int => $recorder->depth);

        // the double has to really run the closure, otherwise no write is ever reached and the test passes vacuously
        $connection->expects($this->atLeastOnce())
            ->method('transactional')
            ->willReturnCallback(static fn (\Closure $closure): mixed => $recorder->transactional($closure, $connection));

        $action(new ProductCategoryDenormalizer($connection));

        return $recorder->writes;
    }

    /**
     * @param list<string> $products
     * @param list<array{transaction: int|null, values: list<mixed>}> $writes
     *
     * @return list<string>
     */
    private static function productsWrittenWith(string $value, array $products, array $writes): array
    {
        $written = [];
        foreach ($writes as $write) {
            if (\in_array($value, $write['values'], true)) {
                $written = [...$written, ...array_intersect($products, $write['values'])];
            }
        }

        return array_values(array_unique($written));
    }
}

/**
 * @internal
 */
class TransactionRecorder
{
    public int $depth = 0;

    /**
     * @var list<array{transaction: int|null, values: list<mixed>}>
     */
    public array $writes = [];

    private int $outermost = 0;

    public function transactional(\Closure $closure, Connection $connection): mixed
    {
        if ($this->depth === 0) {
            ++$this->outermost;
        }

        ++$this->depth;

        try {
            return $closure($connection);
        } finally {
            --$this->depth;
        }
    }

    /**
     * @param array<mixed> $params
     */
    public function write(string $sql, array $params = []): int
    {
        $values = [];
        array_walk_recursive($params, static function (mixed $value) use (&$values): void {
            $values[] = $value;
        });
        $this->writes[] = ['transaction' => $this->depth > 0 ? $this->outermost : null, 'values' => $values];

        return 1;
    }
}
