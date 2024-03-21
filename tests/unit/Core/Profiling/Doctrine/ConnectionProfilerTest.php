<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Profiling\Doctrine;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Profiling\Doctrine\BacktraceDebugDataHolder;
use Shopware\Core\Profiling\Doctrine\ConnectionProfiler;
use Shopware\Core\Profiling\Doctrine\ProfilingMiddleware;
use Symfony\Bridge\Doctrine\Middleware\Debug\Query;
use Symfony\Bridge\PhpUnit\ClockMock;
use Symfony\Component\VarDumper\Cloner\Data;
use Symfony\Component\VarDumper\Dumper\CliDumper;

/**
 * @internal
 */
#[CoversClass(ConnectionProfiler::class)]
#[Group('time-sensitive')]
class ConnectionProfilerTest extends TestCase
{
    protected function setUp(): void
    {
        ClockMock::withClockMock(1500000000);
    }

    public function testCollectConnections(): void
    {
        $c = $this->createCollector([]);
        $c->lateCollect();
        $c = unserialize(serialize($c));
        static::assertInstanceOf(ConnectionProfiler::class, $c);
        static::assertEquals(['default'], $c->getConnections());
    }

    public function testCollectQueryCount(): void
    {
        $c = $this->createCollector([]);
        $c->lateCollect();
        $c = unserialize(serialize($c));
        static::assertInstanceOf(ConnectionProfiler::class, $c);
        static::assertEquals(0, $c->getQueryCount());

        $queries = [
            ['sql' => 'SELECT * FROM table1', 'params' => [], 'types' => [], 'executionMS' => 0],
        ];
        $c = $this->createCollector($queries);
        $c->lateCollect();
        $c = unserialize(serialize($c));
        static::assertInstanceOf(ConnectionProfiler::class, $c);
        static::assertEquals(1, $c->getQueryCount());
    }

    public function testCollectTime(): void
    {
        $c = $this->createCollector([]);
        $c->lateCollect();
        $c = unserialize(serialize($c));
        static::assertInstanceOf(ConnectionProfiler::class, $c);
        static::assertEquals(0, $c->getTime());

        $queries = [
            ['sql' => 'SELECT * FROM table1', 'params' => [], 'types' => [], 'executionMS' => 10],
        ];
        $c = $this->createCollector($queries);
        $c->lateCollect();
        $c = unserialize(serialize($c));
        static::assertInstanceOf(ConnectionProfiler::class, $c);
        static::assertEquals(1, floor($c->getTime() * 100));

        $queries = [
            ['sql' => 'SELECT * FROM table1', 'params' => [], 'types' => [], 'executionMS' => 10],
            ['sql' => 'SELECT * FROM table2', 'params' => [], 'types' => [], 'executionMS' => 20],
        ];
        $c = $this->createCollector($queries);
        $c->lateCollect();
        $c = unserialize(serialize($c));
        static::assertInstanceOf(ConnectionProfiler::class, $c);

        $t = floor($c->getTime() * 100);

        static::assertGreaterThanOrEqual(3, $t);
    }

    public function testCollectQueryWithNoTypes(): void
    {
        $queries = [
            ['sql' => 'SET sql_mode=(SELECT REPLACE(@@sql_mode, \'ONLY_FULL_GROUP_BY\', \'\'))', 'params' => [], 'types' => null, 'executionMS' => 1],
        ];
        $c = $this->createCollector($queries);
        $c->lateCollect();
        $c = unserialize(serialize($c));
        static::assertInstanceOf(ConnectionProfiler::class, $c);

        $collectedQueries = $c->getQueries();
        static::assertSame([], $collectedQueries['default'][0]['types']);
    }

    public function testReset(): void
    {
        $queries = [
            ['sql' => 'SELECT * FROM table1', 'params' => [], 'types' => [], 'executionMS' => 1],
        ];
        $c = $this->createCollector($queries);
        $c->lateCollect();

        $c->reset();
        $c->lateCollect();
        $c = unserialize(serialize($c));
        static::assertInstanceOf(ConnectionProfiler::class, $c);

        static::assertEquals([], $c->getQueries());
    }

    /**
     * @param array<mixed> $types
     */
    #[DataProvider('paramProvider')]
    public function testCollectQueries(mixed $param, array $types, mixed $expected): void
    {
        $queries = [
            ['sql' => 'SELECT * FROM table1 WHERE field1 = ?1', 'params' => [$param], 'types' => $types, 'executionMS' => 1],
        ];
        $c = $this->createCollector($queries);
        $c->lateCollect();
        $c = unserialize(serialize($c));
        static::assertInstanceOf(ConnectionProfiler::class, $c);

        $collectedQueries = $c->getQueries();

        // @phpstan-ignore-next-line
        $collectedParam = $collectedQueries['default'][0]['params'][0];
        if ($collectedParam instanceof Data) {
            $out = fopen('php://memory', 'r+b');
            \assert(\is_resource($out));
            $dumper = new CliDumper();
            $dumper->setColors(false);
            $collectedParam->dump($dumper);
            static::assertStringMatchesFormat($expected, print_r(stream_get_contents($out, -1, 0), true));
        } elseif (\is_string($expected)) {
            static::assertStringMatchesFormat($expected, $collectedParam);
        } else {
            static::assertEquals($expected, $collectedParam);
        }

        static::assertTrue($collectedQueries['default'][0]['explainable']);
        static::assertTrue($collectedQueries['default'][0]['runnable']);
    }

    /**
     * @return array<array{0: mixed, 1: array<mixed>, 2: mixed}>
     */
    public static function paramProvider(): array
    {
        return [
            ['some value', [], 'some value'],
            [1, [], 1],
            [true, [], true],
            [null, [], null],
        ];
    }

    public function testCollectQueryWithNoParams(): void
    {
        $queries = [
            ['sql' => 'SELECT * FROM table1', 'params' => [], 'types' => [], 'executionMS' => 1],
            ['sql' => 'SELECT * FROM table1', 'params' => null, 'types' => null, 'executionMS' => 1],
        ];
        $c = $this->createCollector($queries);
        $c->lateCollect();
        $c = unserialize(serialize($c));
        static::assertInstanceOf(ConnectionProfiler::class, $c);

        $collectedQueries = $c->getQueries();
        static::assertInstanceOf(Data::class, $collectedQueries['default'][0]['params']);
        static::assertEquals([], $collectedQueries['default'][0]['params']->getValue());
        static::assertTrue($collectedQueries['default'][0]['explainable']);
        static::assertTrue($collectedQueries['default'][0]['runnable']);
        static::assertInstanceOf(Data::class, $collectedQueries['default'][1]['params']);
        static::assertEquals([], $collectedQueries['default'][1]['params']->getValue());
        static::assertTrue($collectedQueries['default'][1]['explainable']);
        static::assertTrue($collectedQueries['default'][1]['runnable']);
    }

    /**
     * @param array<mixed> $types
     */
    #[DataProvider('paramProvider')]
    public function testSerialization(mixed $param, array $types, mixed $expected): void
    {
        $queries = [
            ['sql' => 'SELECT * FROM table1 WHERE field1 = ?1', 'params' => [$param], 'types' => $types, 'executionMS' => 1],
        ];
        $c = $this->createCollector($queries);
        $c->lateCollect();
        $c = unserialize(serialize($c));
        static::assertInstanceOf(ConnectionProfiler::class, $c);

        $collectedQueries = $c->getQueries();

        // @phpstan-ignore-next-line
        $collectedParam = $collectedQueries['default'][0]['params'][0];
        if ($collectedParam instanceof Data) {
            $out = fopen('php://memory', 'r+b');
            \assert(\is_resource($out));
            $dumper = new CliDumper();
            $dumper->setColors(false);
            $collectedParam->dump($dumper);
            static::assertStringMatchesFormat($expected, print_r(stream_get_contents($out, -1, 0), true));
        } elseif (\is_string($expected)) {
            static::assertStringMatchesFormat($expected, $collectedParam);
        } else {
            static::assertEquals($expected, $collectedParam);
        }

        static::assertTrue($collectedQueries['default'][0]['explainable']);
        static::assertTrue($collectedQueries['default'][0]['runnable']);
    }

    /**
     * @param array<array{sql: string, params: array<mixed>|null, types: array<mixed>|null, executionMS?: int}> $queries
     */
    private function createCollector(array $queries): ConnectionProfiler
    {
        $debugDataHolder = new BacktraceDebugDataHolder(['default']);
        $config = new Configuration();
        $config->setMiddlewares([new ProfilingMiddleware($debugDataHolder)]);

        $connection = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->getMock();
        $connection->expects(static::any())
            ->method('getDatabasePlatform')
            ->willReturn(new MySQLPlatform());
        $connection->expects(static::any())
            ->method('getConfiguration')
            ->willReturn($config);

        $collector = new ConnectionProfiler($connection);
        foreach ($queries as $queryData) {
            $query = new Query($queryData['sql'] ?? '');
            foreach (($queryData['params'] ?? []) as $key => $value) {
                if (\is_int($key)) {
                    ++$key;
                }

                $query->setValue($key, $value, $queryData['types'][$key] ?? ParameterType::STRING);
            }

            $query->start();

            $debugDataHolder->addQuery('default', $query);

            if (isset($queryData['executionMS'])) {
                usleep($queryData['executionMS'] * 1000);
            }
            $query->stop();
        }

        return $collector;
    }
}
