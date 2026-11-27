<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Profiling\Export\Summarizer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Profiling\Doctrine\ConnectionProfiler;
use Shopware\Core\Profiling\Export\Summarizer\DatabaseSummarizer;
use Symfony\Component\HttpKernel\DataCollector\TimeDataCollector;
use Symfony\Component\VarDumper\Cloner\VarCloner;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DatabaseSummarizer::class)]
class DatabaseSummarizerTest extends TestCase
{
    private const LOADER_TRACE = [
        ['class' => 'Doctrine\\DBAL\\Connection', 'function' => 'executeQuery', 'file' => '/app/src/Core/Framework/DataAbstractionLayer/Dbal/EntityReader.php', 'line' => 10],
        ['class' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\Dbal\\EntityReader', 'function' => 'read', 'file' => '/app/src/Core/Framework/DataAbstractionLayer/EntityRepository.php', 'line' => 20],
        ['class' => 'Shopware\\Core\\Framework\\DataAbstractionLayer\\EntityRepository', 'function' => 'search', 'file' => '/app/src/Core/Content/Product/ProductLoader.php', 'line' => 42],
        ['class' => 'Shopware\\Core\\Content\\Product\\ProductLoader', 'function' => 'load', 'file' => '/app/src/Core/Content/Product/ProductController.php', 'line' => 7],
    ];

    public function testSupportsOnlyTheConnectionProfiler(): void
    {
        static::assertTrue((new DatabaseSummarizer())->supports(static::createStub(ConnectionProfiler::class)));
        static::assertFalse((new DatabaseSummarizer())->supports(new TimeDataCollector()));
    }

    public function testGroupsStatementsWithTimesDuplicatesAndCallers(): void
    {
        $collector = $this->collector([
            'default' => [
                $this->query("SELECT *\n  FROM product WHERE id IN (?, ?, ?)", ['a', 'b', 'c'], 0.002, self::LOADER_TRACE),
                $this->query('SELECT * FROM product WHERE id IN (?, ?)', ['a', 'b'], 0.004, self::LOADER_TRACE),
                $this->query('SELECT * FROM product WHERE id IN (?, ?)', ['a', 'b'], 0.001),
                $this->query('SELECT 1', [], 0.0005),
            ],
            'replica' => [
                $this->query('SELECT 1', [], 0.001),
            ],
        ]);

        static::assertSame(
            [
                'queries' => 5,
                'timeMs' => 8.5,
                'statements' => 3,
                'identicalRuns' => 1,
                'top' => [
                    [
                        'sql' => 'SELECT * FROM product WHERE id IN (?…)',
                        'count' => 3,
                        'totalMs' => 7.0,
                        'maxMs' => 4.0,
                        'identicalRuns' => 1,
                        'callers' => ['Shopware\\Core\\Content\\Product\\ProductLoader::load (ProductLoader.php:42)' => 2],
                    ],
                    ['sql' => 'SELECT 1', 'count' => 1, 'totalMs' => 1.0, 'maxMs' => 1.0, 'identicalRuns' => 0, 'connection' => 'replica'],
                    ['sql' => 'SELECT 1', 'count' => 1, 'totalMs' => 0.5, 'maxMs' => 0.5, 'identicalRuns' => 0],
                ],
            ],
            (new DatabaseSummarizer())->summarize($collector, null),
        );
    }

    public function testListsTheSlowestStatementsAndTheMostRepeatedOnes(): void
    {
        $queries = [];
        for ($i = 1; $i <= 20; ++$i) {
            $queries[] = $this->query('SELECT ' . $i . ' FROM slow', [], $i / 10);
        }
        for ($i = 0; $i < 30; ++$i) {
            $queries[] = $this->query('SELECT name FROM repeated WHERE id = ?', [$i], 0.00001);
        }

        $summary = (new DatabaseSummarizer())->summarize($this->collector(['default' => $queries]), null);
        $statements = array_column($summary['top'], 'sql');

        static::assertCount(16, $statements);
        static::assertSame('SELECT 20 FROM slow', $statements[0]);
        static::assertSame('SELECT 6 FROM slow', $statements[14]);
        static::assertSame('SELECT name FROM repeated WHERE id = ?', $statements[15]);
        static::assertSame(30, $summary['top'][15]['count']);
    }

    /**
     * @param array<string, list<array<string, mixed>>> $queries
     */
    private function collector(array $queries): ConnectionProfiler
    {
        $collector = static::createStub(ConnectionProfiler::class);
        $collector->method('getQueries')->willReturn($queries);

        return $collector;
    }

    /**
     * @param list<mixed> $params
     * @param list<array<string, mixed>> $backtrace
     *
     * @return array<string, mixed>
     */
    private function query(string $sql, array $params, float $seconds, array $backtrace = []): array
    {
        $query = [
            'sql' => $sql,
            'executionMS' => $seconds,
            'types' => [],
            'params' => (new VarCloner())->cloneVar($params),
            'runnable' => true,
            'explainable' => true,
        ];

        if ($backtrace !== []) {
            $query['backtrace'] = $backtrace;
        }

        return $query;
    }
}
