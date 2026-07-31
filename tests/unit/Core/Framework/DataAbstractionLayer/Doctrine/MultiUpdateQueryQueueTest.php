<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Doctrine;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\MultiUpdateQueryQueue;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @phpstan-type Updates list<array{key: string, columns: array<string, string|int|float|null>}>
 * @phpstan-type Queries list<array{query: string, values: list<string|int|float>}>
 */
#[Package('framework')]
#[CoversClass(MultiUpdateQueryQueue::class)]
class MultiUpdateQueryQueueTest extends TestCase
{
    /**
     * @param Updates $updates
     * @param Queries $queries
     * @param array<string, string|int|float> $conditions
     */
    #[DataProvider('preparedQueriesDataProvider')]
    public function testPrepareQueries(array $updates, array $queries, array $conditions = [], int $chunkSize = 250): void
    {
        $executed = [];
        $queue = new MultiUpdateQueryQueue($this->createRecordingConnection($executed), 'product', 'id', $chunkSize);
        foreach ($updates as $update) {
            $queue->addUpdate($update['key'], $update['columns']);
        }

        $queue->execute($conditions);

        static::assertCount(\count($queries), $executed);
        foreach ($executed as $index => $query) {
            static::assertSame($queries[$index]['query'], $query['query']);
            static::assertSame($queries[$index]['values'], $query['values']);
        }
    }

    /**
     * @return iterable<string, array{
     *     updates: Updates,
     *     queries: Queries,
     *     conditions?: array<string, string|int|float>,
     *     chunkSize?: int
     * }>
     */
    public static function preparedQueriesDataProvider(): iterable
    {
        yield 'single column update with condition' => [
            'updates' => [
                ['key' => 'key1', 'columns' => ['category_tree' => '["a"]']],
                ['key' => 'key2', 'columns' => ['category_tree' => '["b"]']],
            ],
            'queries' => [
                [
                    'query' => 'UPDATE `product` SET `category_tree` = CASE `id` WHEN ? THEN ? WHEN ? THEN ? ELSE `category_tree` END WHERE `id` IN (?,?) AND `version_id` = ?;',
                    'values' => ['key1', '["a"]', 'key2', '["b"]', 'key1', 'key2', 'version'],
                ],
            ],
            'conditions' => ['version_id' => 'version'],
        ];

        yield 'null values are written as NULL' => [
            'updates' => [
                ['key' => 'key1', 'columns' => ['category_tree' => null]],
                ['key' => 'key2', 'columns' => ['category_tree' => '["b"]']],
            ],
            'queries' => [
                [
                    'query' => 'UPDATE `product` SET `category_tree` = CASE `id` WHEN ? THEN NULL WHEN ? THEN ? ELSE `category_tree` END WHERE `id` IN (?,?);',
                    'values' => ['key1', 'key2', '["b"]', 'key1', 'key2'],
                ],
            ],
        ];

        yield 'multi column update' => [
            'updates' => [
                ['key' => 'key1', 'columns' => ['path' => '|a|', 'level' => 2]],
            ],
            'queries' => [
                [
                    'query' => 'UPDATE `product` SET `path` = CASE `id` WHEN ? THEN ? ELSE `path` END, `level` = CASE `id` WHEN ? THEN ? ELSE `level` END WHERE `id` IN (?);',
                    'values' => ['key1', '|a|', 'key1', 2, 'key1'],
                ],
            ],
        ];

        yield 'chunking' => [
            'updates' => [
                ['key' => 'key1', 'columns' => ['stock' => 1]],
                ['key' => 'key2', 'columns' => ['stock' => 2]],
                ['key' => 'key3', 'columns' => ['stock' => 3]],
            ],
            'queries' => [
                [
                    'query' => 'UPDATE `product` SET `stock` = CASE `id` WHEN ? THEN ? WHEN ? THEN ? ELSE `stock` END WHERE `id` IN (?,?);',
                    'values' => ['key1', 1, 'key2', 2, 'key1', 'key2'],
                ],
                [
                    'query' => 'UPDATE `product` SET `stock` = CASE `id` WHEN ? THEN ? ELSE `stock` END WHERE `id` IN (?);',
                    'values' => ['key3', 3, 'key3'],
                ],
            ],
            'chunkSize' => 2,
        ];

        yield 'different column sets are updated separately' => [
            'updates' => [
                ['key' => 'key1', 'columns' => ['cheapest_price' => 'price']],
                ['key' => 'key2', 'columns' => ['cheapest_price_accessor' => 'accessor']],
            ],
            'queries' => [
                [
                    'query' => 'UPDATE `product` SET `cheapest_price` = CASE `id` WHEN ? THEN ? ELSE `cheapest_price` END WHERE `id` IN (?);',
                    'values' => ['key1', 'price', 'key1'],
                ],
                [
                    'query' => 'UPDATE `product` SET `cheapest_price_accessor` = CASE `id` WHEN ? THEN ? ELSE `cheapest_price_accessor` END WHERE `id` IN (?);',
                    'values' => ['key2', 'accessor', 'key2'],
                ],
            ],
        ];

        yield 'last update for the same key and columns wins' => [
            'updates' => [
                ['key' => 'key1', 'columns' => ['stock' => 1]],
                ['key' => 'key1', 'columns' => ['stock' => 2]],
            ],
            'queries' => [
                [
                    'query' => 'UPDATE `product` SET `stock` = CASE `id` WHEN ? THEN ? ELSE `stock` END WHERE `id` IN (?);',
                    'values' => ['key1', 2, 'key1'],
                ],
            ],
        ];

        yield 'updates without columns are ignored' => [
            'updates' => [
                ['key' => 'key1', 'columns' => []],
            ],
            'queries' => [],
        ];
    }

    public function testExecuteWithoutUpdatesDoesNotTouchTheConnection(): void
    {
        $executed = [];
        $queue = new MultiUpdateQueryQueue($this->createRecordingConnection($executed), 'product');

        $queue->execute();

        static::assertSame([], $executed);
    }

    public function testExecuteClearsTheQueue(): void
    {
        $executed = [];
        $queue = new MultiUpdateQueryQueue($this->createRecordingConnection($executed), 'product');
        $queue->addUpdate('key1', ['stock' => 1]);

        $queue->execute();
        $queue->execute();

        static::assertCount(1, $executed);
    }

    public function testConstructorThrowsOnWrongChunkSize(): void
    {
        $connection = static::createStub(Connection::class);
        self::expectExceptionObject(DataAbstractionLayerException::invalidChunkSize(0));
        new MultiUpdateQueryQueue($connection, 'product', 'id', 0);
    }

    /**
     * Records the statements the queue executes, so the generated SQL can be asserted afterwards.
     *
     * @param list<array{query: string, values: array<mixed>}> $executed
     */
    private function createRecordingConnection(array &$executed): Connection
    {
        $connection = static::createStub(Connection::class);
        $connection->method('transactional')->willReturnCallback(
            static fn (\Closure $callback): mixed => $callback($connection)
        );
        $connection->method('executeStatement')->willReturnCallback(
            function (string $sql, array $params = []) use (&$executed): int {
                $executed[] = ['query' => $sql, 'values' => $params];

                return 1;
            }
        );

        return $connection;
    }
}
