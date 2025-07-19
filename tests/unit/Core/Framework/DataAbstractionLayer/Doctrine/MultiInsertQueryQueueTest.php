<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\MultiInsertQueryQueue;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;

/**
 * @internal
 *
 * @phpstan-type Inserts list<array{
 *          table: string,
 *          data: array<string, mixed>,
 *          types?: array<string, ParameterType>
 *      }>
 * @phpstan-type Queries list<array{
 *          query: string,
 *          values: list<mixed>,
 *          types?: list<ParameterType>
 *      }>
 */
#[Package('framework')]
#[CoversClass(MultiInsertQueryQueue::class)]
class MultiInsertQueryQueueTest extends TestCase
{
    /**
     * @param Inserts $inserts
     * @param Queries $queries
     */
    #[DataProvider('preparedQueriesDataProvider')]
    public function testPrepareQueries(array $inserts, array $queries, int $batchSize = 5, bool $ignoreErrors = false, bool $useReplace = false): void
    {
        $queue = new MultiInsertQueryQueue($this->createMock(Connection::class), $batchSize, $ignoreErrors, $useReplace);
        foreach ($inserts as $insert) {
            $queue->addInsert($insert['table'], $insert['data'], $insert['types'] ?? null);
        }

        $prepareQueries = ReflectionHelper::getMethod(MultiInsertQueryQueue::class, 'prepareQueries');
        $generatedQueries = $prepareQueries->invoke($queue);

        static::assertIsArray($generatedQueries);
        static::assertCount(\count($queries), $generatedQueries);
        foreach ($generatedQueries as $index => $query) {
            static::assertArrayHasKey('query', $query);
            static::assertArrayHasKey('values', $query);
            static::assertArrayHasKey('types', $query);
            static::assertSame($queries[$index]['query'], $query['query']);
            static::assertSame($queries[$index]['values'], $query['values']);
            // we don't want to provide types for default values
            $types = $queries[$index]['types'] ?? array_fill(0, \count($queries[$index]['values']), ParameterType::STRING);
            static::assertSame($types, $query['types']);
        }
    }

    /**
     * @return iterable<string, array{
     *     inserts: Inserts,
     *     queries: Queries,
     *     batchSize?: int,
     *     ignoreErrors?: bool,
     *     useReplace?: bool
     * }>
     */
    public static function preparedQueriesDataProvider(): iterable
    {
        yield 'single insert with types' => [
            'inserts' => [
                [
                    'table' => 'table1',
                    'data' => ['id' => 1, 'name' => 'test'],
                    'types' => [
                        'id' => ParameterType::INTEGER,
                        'name' => ParameterType::STRING,
                    ],
                ],
            ],
            'queries' => [
                [
                    'query' => 'INSERT INTO `table1` (`id`, `name`) VALUES (?,?);',
                    'values' => [1, 'test'],
                    'types' => [ParameterType::INTEGER, ParameterType::STRING],
                ],
            ],
        ];

        yield 'default types' => [
            'inserts' => [
                [
                    'table' => 'table1',
                    'data' => ['id' => 1, 'name' => 'test'],
                ],
            ],
            'queries' => [
                [
                    'query' => 'INSERT INTO `table1` (`id`, `name`) VALUES (?,?);',
                    'values' => [1, 'test'],
                ],
            ],
        ];

        yield 'batching' => [
            'inserts' => [
                ['table' => 'table1', 'data' => ['id' => 1]],
                ['table' => 'table1', 'data' => ['id' => 2]],
                ['table' => 'table1', 'data' => ['id' => 3]],
                ['table' => 'table1', 'data' => ['id' => 4]],
                ['table' => 'table1', 'data' => ['id' => 5]],
            ],
            'queries' => [
                [
                    'query' => 'INSERT INTO `table1` (`id`) VALUES (?), (?);',
                    'values' => [1, 2],
                ],
                [
                    'query' => 'INSERT INTO `table1` (`id`) VALUES (?), (?);',
                    'values' => [3, 4],
                ],
                [
                    'query' => 'INSERT INTO `table1` (`id`) VALUES (?);',
                    'values' => [5],
                ],
            ],
            'batchSize' => 2,
        ];

        yield 'ignore errors' => [
            'inserts' => [
                ['table' => 'table1', 'data' => ['id' => 1]],
                ['table' => 'table1', 'data' => ['id' => 2]],
            ],
            'queries' => [
                [
                    'query' => 'INSERT IGNORE INTO `table1` (`id`) VALUES (?), (?);',
                    'values' => [1, 2],
                ],
            ],
            'batchSize' => 5,
            'ignoreErrors' => true,
        ];

        yield 'use replace' => [
            'inserts' => [
                ['table' => 'table1', 'data' => ['id' => 1]],
                ['table' => 'table1', 'data' => ['id' => 2]],
            ],
            'queries' => [
                [
                    'query' => 'REPLACE INTO `table1` (`id`) VALUES (?), (?);',
                    'values' => [1, 2],
                ],
            ],
            'batchSize' => 5,
            'ignoreErrors' => true, // ignore errors is ignored when using replace
            'useReplace' => true,
        ];

        yield 'multiple inserts, inconsistent and null columns' => [
            'inserts' => [
                [
                    'table' => 'table1',
                    'data' => [
                        'id' => 1,
                        'name' => 'test_n_1',
                        'description' => 'test_d_1',
                    ],
                ],
                [
                    'table' => 'table1',
                    'data' => [
                        'id' => 2,
                        'name' => 'test_n_2',
                        'description' => null, // nulls should be inserted as NULL
                    ],
                    'types' => [
                        'id' => ParameterType::INTEGER,
                        'description' => ParameterType::STRING, // this should be ignored as field will be default
                        'bla' => ParameterType::LARGE_OBJECT, // this should be ignored as field does not exist in input
                    ],
                ],
                [
                    'table' => 'table1',
                    'data' => [
                        'id' => 3,
                        'name' => 'test_n_3',
                        // missing description should be replaced with DEFAULT
                    ],
                ],
                [
                    'table' => 'table2',
                    'data' => [
                        'tag' => 'test_tag',
                    ],
                ],
            ],
            'queries' => [
                [
                    'query' => 'INSERT INTO `table1` (`id`, `name`, `description`) VALUES (?,?,?), (?,?,NULL), (?,?,DEFAULT);',
                    'values' => [1, 'test_n_1', 'test_d_1', 2, 'test_n_2', 3, 'test_n_3'],
                    'types' => [ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER,  ParameterType::STRING, ParameterType::STRING, ParameterType::STRING],
                ],
                [
                    'query' => 'INSERT INTO `table2` (`tag`) VALUES (?);',
                    'values' => ['test_tag'],
                ],
            ],
        ];
    }

    public function testAddInserts(): void
    {
        $queue = new MultiInsertQueryQueue($this->createMock(Connection::class));
        $queue->addInserts('table1', [
            ['id' => 1, 'name' => 'test1', 'description' => 'test1'],
            ['id' => 2, 'name' => 'test2', 'description' => 'test2'],
        ]);

        $prepareQueries = ReflectionHelper::getMethod(MultiInsertQueryQueue::class, 'prepareQueries');
        $generatedQueries = $prepareQueries->invoke($queue);

        static::assertIsArray($generatedQueries);
        static::assertCount(1, $generatedQueries);
        $query = reset($generatedQueries);

        static::assertArrayHasKey('query', $query);
        static::assertArrayHasKey('values', $query);
        static::assertArrayHasKey('types', $query);
        static::assertSame('INSERT INTO `table1` (`id`, `name`, `description`) VALUES (?,?,?), (?,?,?);', $query['query']);
        static::assertSame([1, 'test1', 'test1', 2, 'test2', 'test2'], $query['values']);
        static::assertSame(
            array_fill(0, 6, ParameterType::STRING),
            $query['types']
        );
    }

    public function testUpdateOnDuplicateKeys(): void
    {
        $queue = new MultiInsertQueryQueue($this->createMock(Connection::class));
        $queue->addInsert('table1', ['id' => 1, 'name' => 'test1', 'description' => 'test1']);
        $queue->addInsert('table1', ['id' => 2, 'name' => 'test2', 'description' => 'test2']);
        $queue->addUpdateFieldOnDuplicateKey('table1', 'name');
        $queue->addUpdateFieldOnDuplicateKey('table1', 'non_existing_field');
        $queue->addUpdateFieldOnDuplicateKey('table1', 'description');

        $prepareQueries = ReflectionHelper::getMethod(MultiInsertQueryQueue::class, 'prepareQueries');
        $generatedQueries = $prepareQueries->invoke($queue);
        static::assertIsArray($generatedQueries);
        static::assertCount(1, $generatedQueries);
        $query = reset($generatedQueries);

        static::assertArrayHasKey('query', $query);
        static::assertArrayHasKey('values', $query);
        static::assertArrayHasKey('types', $query);
        static::assertSame('INSERT INTO `table1` (`id`, `name`, `description`) VALUES (?,?,?), (?,?,?) ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `description` = VALUES(`description`);', $query['query']);
        static::assertSame([1, 'test1', 'test1', 2, 'test2', 'test2'], $query['values']);
        static::assertSame(
            array_fill(0, 6, ParameterType::STRING),
            $query['types']
        );
    }

    public function testConstructorThrowsOnWrongBatchSize(): void
    {
        $connection = $this->createMock(Connection::class);
        self::expectExceptionObject(DataAbstractionLayerException::invalidChunkSize(0));
        new MultiInsertQueryQueue($connection, 0);
    }
}
