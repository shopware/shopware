<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Seo;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\SeoUrlPersister;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\PHPUnit\CompletionGuard\CompletionGuard;

/**
 * Regression test for the category/seo-url indexing deadlock of shopware/shopware#6540 (NEXT-22174).
 *
 * Concurrent url generation (e.g. parallel `POST /api/_action/sync` category writes) makes several
 * {@see SeoUrlPersister} runs write overlapping `seo_url` rows at the same time. The persister writes
 * each batch in a single retryable transaction, and how those transactions lock the shared rows
 * depends heavily on the write strategy:
 *
 *   - `REPLACE INTO` (the original code) deletes and re-inserts every row, taking wide next-key locks
 *     and rewriting every secondary index, which deadlocks under REPEATABLE READ.
 *   - `INSERT ... ON DUPLICATE KEY UPDATE` under READ COMMITTED removes the next-key locks that would
 *     otherwise serialize overlapping batches, so the multi-statement transaction forms cross-statement
 *     lock cycles instead - producing *more* deadlocks, not fewer.
 *   - `INSERT ... ON DUPLICATE KEY UPDATE` under the default REPEATABLE READ updates the colliding row
 *     in place with a small lock footprint, and the next-key locks make overlapping batches queue
 *     cleanly. This is the combination the fix keeps, and this test guards it.
 *
 * The test drives the real persister from several forked workers against committed rows, so it needs
 * genuinely concurrent connections. It therefore runs OUTSIDE the usual test transaction and is
 * opt-in (set `RUN_DEADLOCK_REPRO=1`) so it never slows down or destabilises the default CI run.
 *
 * @internal
 */
#[Package('inventory')]
#[Group('quarantined')]
class SeoUrlPersisterConcurrencyTest extends TestCase
{
    use KernelTestBehaviour;

    private const ROUTE_NAME = 'test.seo.deadlock.repro';

    // a small, hot shared pool worked on by many writers in a tight loop is the worst case for lock
    // ordering and reliably reproduces the deadlock on the unfixed code while staying well under a second
    private const POOL_SIZE = 20;
    private const WORKERS = 12;
    private const ROUNDS = 30;

    private const EXIT_DEADLOCK = 42;
    private const EXIT_OTHER_ERROR = 1;

    private Connection $connection;

    private SeoUrlPersister $persister;

    /**
     * @var list<string>
     */
    private array $foreignKeys = [];

    private string $salesChannelId;

    protected function setUp(): void
    {
        if (getenv('RUN_DEADLOCK_REPRO') !== '1') {
            static::markTestSkipped('Opt-in concurrency reproduction. Set RUN_DEADLOCK_REPRO=1 to run it.');
        }

        if (!\function_exists('pcntl_fork')) {
            static::markTestSkipped('The pcntl extension is required for the concurrency reproduction.');
        }

        $this->connection = static::getContainer()->get(Connection::class);
        $this->persister = static::getContainer()->get(SeoUrlPersister::class);

        try {
            $this->raisedDeadlockCount();
        } catch (\Throwable) {
            static::markTestSkipped('performance_schema error statistics are required to measure deadlocks.');
        }

        // a real sales channel id is required: the seo_url unique indexes only enforce uniqueness
        // (and therefore only produce the colliding locks) when sales_channel_id is not NULL
        $salesChannelId = $this->connection->fetchOne('SELECT LOWER(HEX(id)) FROM sales_channel LIMIT 1');
        if (!\is_string($salesChannelId)) {
            static::markTestSkipped('At least one sales channel is required for the reproduction.');
        }
        $this->salesChannelId = $salesChannelId;

        $this->cleanupReproRows();
        $this->seedSharedPool();
    }

    protected function tearDown(): void
    {
        @unlink($this->startBarrierPath());
        if (isset($this->connection)) {
            $this->cleanupReproRows();
        }
    }

    public function testConcurrentUrlGenerationDoesNotDeadlock(): void
    {
        $deadlocksBefore = $this->raisedDeadlockCount();

        // Close the shared connection before forking. A forked child inherits a copy of the
        // parent's file descriptor pointing at the SAME socket, so if any process used or closed
        // it the others would see "MySQL server has gone away". With the socket closed up front,
        // the parent and every worker reconnect lazily, each on its own independent connection.
        $this->connection->close();

        // release-barrier file: workers block until the parent creates it, so every worker starts
        // hammering the shared rows at the same moment and their lock windows overlap maximally
        $startBarrier = $this->startBarrierPath();
        @unlink($startBarrier);

        $childPids = [];
        for ($worker = 0; $worker < self::WORKERS; ++$worker) {
            $pid = pcntl_fork();
            static::assertNotSame(-1, $pid, 'Failed to fork worker process.');

            if ($pid === 0) {
                // this forked child terminates via exit() below; mark the run finished so the
                // TestBootstrapper CompletionGuard treats the child as inert and does not override
                // its exit code (isolated child processes are handled the same way)
                CompletionGuard::$executionFinished = true;
                exit($this->runWorker($worker, $startBarrier));
            }

            $childPids[$worker] = $pid;
        }

        // let every worker reach the barrier, then release them together
        usleep(200000);
        touch($startBarrier);

        $deadlocked = [];
        $errored = [];
        foreach ($childPids as $worker => $pid) {
            pcntl_waitpid($pid, $status);
            $exit = pcntl_wexitstatus($status);
            if ($exit === self::EXIT_DEADLOCK) {
                $deadlocked[] = $worker;
            } elseif ($exit !== 0) {
                $errored[] = $worker;
            }
        }

        $deadlocksDuringRun = $this->raisedDeadlockCount() - $deadlocksBefore;

        static::assertSame([], $errored, 'Some workers failed with a non-deadlock error: ' . $this->readWorkerErrors($errored));
        static::assertSame(
            [],
            $deadlocked,
            \sprintf(
                'A deadlock (SQLSTATE 40001) escaped the retry budget in %d of %d concurrent workers.',
                \count($deadlocked),
                self::WORKERS
            )
        );

        // The retry loop hides individual deadlocks from the caller, so also assert on the raw number
        // of InnoDB deadlock *cycles* the server reported (error 1213, retried ones included). With the
        // in-place upsert under REPEATABLE READ overlapping url generations never form a lock cycle;
        // with REPLACE INTO or under READ COMMITTED this counter climbs into the dozens or hundreds for
        // the same workload.
        static::assertSame(
            0,
            $deadlocksDuringRun,
            \sprintf(
                '%d InnoDB deadlock cycles (error 1213) were raised while %d workers wrote overlapping '
                . 'seo_url rows concurrently.',
                $deadlocksDuringRun,
                self::WORKERS
            )
        );
    }

    /**
     * Total number of InnoDB deadlock cycles (error 1213) the server has reported since startup,
     * read from performance_schema. This counts every raised deadlock, including those that the
     * retry loop transparently recovered from.
     */
    private function raisedDeadlockCount(): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT SUM_ERROR_RAISED FROM performance_schema.events_errors_summary_global_by_error WHERE ERROR_NUMBER = 1213'
        );
    }

    private function runWorker(int $worker, string $startBarrier): int
    {
        // make every worker walk the shared pool in a different order to maximise lock-order conflicts
        mt_srand(1000 + $worker);

        $context = Context::createDefaultContext();
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId($this->salesChannelId);

        // block until the parent releases all workers at once
        $deadline = microtime(true) + 5.0;
        while (!is_file($startBarrier) && microtime(true) < $deadline) {
            usleep(1000);
        }

        try {
            for ($round = 0; $round < self::ROUNDS; ++$round) {
                // Every worker updates the same shared set of foreign keys, but each in a different
                // order and toggling every url to a fresh path each round (mimicking concurrent
                // category reparenting, which rewrites the seo path). Each foreign key owns a private
                // pair of paths, so there is no cross-row data conflict - only the lock ordering
                // differs, which is exactly what makes concurrent transactions deadlock on the shared
                // seo_url rows unless the persister writes them in a stable order.
                $order = array_keys($this->foreignKeys);
                shuffle($order);

                $seoUrls = [];
                $foreignKeys = [];
                foreach ($order as $k) {
                    $foreignKeys[] = $this->foreignKeys[$k];
                    $seoUrls[] = [
                        'foreignKey' => $this->foreignKeys[$k],
                        'salesChannelId' => $this->salesChannelId,
                        'routeName' => self::ROUTE_NAME,
                        'pathInfo' => '/detail/' . $k,
                        'seoPathInfo' => \sprintf('repro-%d-%s', $k, $round % 2 === 0 ? 'a' : 'b'),
                    ];
                }

                $this->persister->updateSeoUrls($context, self::ROUTE_NAME, $foreignKeys, $seoUrls, $salesChannel);
            }
        } catch (\Throwable $e) {
            if ($this->isDeadlock($e)) {
                return self::EXIT_DEADLOCK;
            }

            file_put_contents($this->workerLogPath($worker), $e::class . ': ' . $e->getMessage());

            return self::EXIT_OTHER_ERROR;
        }

        return 0;
    }

    /**
     * @param list<int> $workers
     */
    private function readWorkerErrors(array $workers): string
    {
        $messages = [];
        foreach ($workers as $worker) {
            $path = $this->workerLogPath($worker);
            if (is_file($path)) {
                $messages[] = \sprintf('[worker %d] %s', $worker, (string) file_get_contents($path));
                unlink($path);
            }
        }

        return implode(' | ', $messages);
    }

    private function workerLogPath(int $worker): string
    {
        return sys_get_temp_dir() . '/seo_repro_worker_' . $worker . '.log';
    }

    private function startBarrierPath(): string
    {
        return sys_get_temp_dir() . '/seo_repro_start_barrier';
    }

    private function isDeadlock(\Throwable $e): bool
    {
        for ($current = $e; $current !== null; $current = $current->getPrevious()) {
            if (str_contains($current->getMessage(), '1213') || str_contains($current->getMessage(), '40001')) {
                return true;
            }
        }

        return false;
    }

    private function seedSharedPool(): void
    {
        $languageId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $now = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        for ($i = 0; $i < self::POOL_SIZE; ++$i) {
            $fk = Uuid::randomHex();
            $this->foreignKeys[] = $fk;

            $this->connection->insert('seo_url', [
                'id' => Uuid::randomBytes(),
                'language_id' => $languageId,
                'sales_channel_id' => Uuid::fromHexToBytes($this->salesChannelId),
                'path_info' => '/detail/' . $i,
                'seo_path_info' => 'repro-' . $i . '-a',
                'foreign_key' => Uuid::fromHexToBytes($fk),
                'route_name' => self::ROUTE_NAME,
                'is_canonical' => 1,
                'is_modified' => 0,
                'is_deleted' => 0,
                'created_at' => $now,
            ]);
        }
    }

    private function cleanupReproRows(): void
    {
        $this->connection->executeStatement(
            'DELETE FROM seo_url WHERE route_name = :route',
            ['route' => self::ROUTE_NAME]
        );
    }
}
