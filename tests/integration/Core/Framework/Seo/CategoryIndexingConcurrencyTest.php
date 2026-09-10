<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Seo;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Seo\SeoUrlPersister;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\ChildCountUpdater;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\PHPUnit\CompletionGuard\CompletionGuard;

/**
 * Regression test for the category indexing deadlock of shopware/shopware#6540 (NEXT-22174).
 *
 * A concurrent `POST /api/_action/sync` category write runs two indexing updaters that both deadlocked
 * under load, for different reasons and on different statements. This test drives each real updater from
 * several forked workers and asserts the server raises zero InnoDB deadlock cycles:
 *
 *   - {@see SeoUrlPersister}: `REPLACE INTO` (a DELETE+INSERT with wide next-key locks over both unique
 *     indexes of `seo_url`) deadlocks under REPEATABLE READ; the `INSERT ... ON DUPLICATE KEY UPDATE`
 *     in-place upsert does not.
 *   - {@see ChildCountUpdater}: the self-joined `UPDATE` takes shared locks on all child rows (subquery)
 *     and exclusive locks on the parents in one statement, so it deadlocks against concurrent child
 *     writes (the classic S->X upgrade); the non-locking `SELECT` + primary-key-only `UPDATE` does not.
 *
 * Because a real deadlock needs genuinely concurrent connections, the workers are forked and run OUTSIDE
 * the usual test transaction. The scenario is opt-in (set `RUN_DEADLOCK_REPRO=1`) so it never slows down
 * or destabilises the default CI run.
 *
 * @internal
 */
#[Package('discovery')]
#[Group('quarantined')]
class CategoryIndexingConcurrencyTest extends TestCase
{
    use KernelTestBehaviour;

    private const ROUTE_NAME = 'test.seo.deadlock.repro';

    // small, hot shared pools worked on by many writers in a tight loop are the worst case for lock
    // ordering and reliably reproduce the deadlocks on the unfixed code while staying well under a second
    private const WORKERS = 12;
    private const ROUNDS = 40;
    private const SEO_POOL = 20;
    private const CATEGORY_PARENTS = 8;
    private const CATEGORY_CHILDREN = 60;

    private const EXIT_DEADLOCK = 42;
    private const EXIT_OTHER_ERROR = 1;

    private Connection $connection;

    private SeoUrlPersister $seoUrlPersister;

    private ChildCountUpdater $childCountUpdater;

    private string $salesChannelId;

    /**
     * @var list<string>
     */
    private array $foreignKeys = [];

    /**
     * @var list<string>
     */
    private array $parentCategoryIds = [];

    /**
     * @var list<string>
     */
    private array $childCategoryIds = [];

    protected function setUp(): void
    {
        if (getenv('RUN_DEADLOCK_REPRO') !== '1') {
            static::markTestSkipped('Opt-in concurrency reproduction. Set RUN_DEADLOCK_REPRO=1 to run it.');
        }

        if (!\function_exists('pcntl_fork')) {
            static::markTestSkipped('The pcntl extension is required for the concurrency reproduction.');
        }

        $this->connection = static::getContainer()->get(Connection::class);
        $this->seoUrlPersister = static::getContainer()->get(SeoUrlPersister::class);
        $this->childCountUpdater = static::getContainer()->get(ChildCountUpdater::class);

        try {
            $this->raisedDeadlockCount();
        } catch (\Throwable) {
            static::markTestSkipped('performance_schema error statistics are required to measure deadlocks.');
        }

        $this->cleanupReproRows();
    }

    protected function tearDown(): void
    {
        @unlink($this->startBarrierPath());
        if (isset($this->connection)) {
            $this->cleanupReproRows();
        }
    }

    public function testConcurrentSeoUrlGenerationDoesNotDeadlock(): void
    {
        // a real sales channel id is required: the seo_url unique indexes only enforce uniqueness
        // (and therefore only produce the colliding locks) when sales_channel_id is not NULL
        $salesChannelId = $this->connection->fetchOne('SELECT LOWER(HEX(id)) FROM sales_channel LIMIT 1');
        if (!\is_string($salesChannelId)) {
            static::markTestSkipped('At least one sales channel is required for the reproduction.');
        }
        $this->salesChannelId = $salesChannelId;

        $this->seedSeoUrlPool();

        $this->assertNoDeadlocksWhile(
            fn (int $worker) => $this->generateSeoUrls($worker),
            'wrote overlapping seo_url rows concurrently',
        );
    }

    public function testConcurrentChildCountRecalculationDoesNotDeadlock(): void
    {
        $this->seedCategoryTree();

        $this->assertNoDeadlocksWhile(
            fn (int $worker) => $this->recalculateChildCountsWhileReparenting($worker),
            'recalculated child counts while categories were reparented concurrently',
        );
    }

    /**
     * Runs $work in {@see self::WORKERS} forked processes, released together by a barrier so their lock
     * windows overlap, and asserts no worker hit a deadlock the retry loop could not absorb and that the
     * server reported zero InnoDB deadlock cycles (error 1213, retried ones included).
     *
     * @param \Closure(int): void $work the per-worker body, receiving the worker index
     */
    private function assertNoDeadlocksWhile(\Closure $work, string $what): void
    {
        $deadlocksBefore = $this->raisedDeadlockCount();

        // Close the shared connection before forking. A forked child inherits a copy of the parent's
        // file descriptor pointing at the SAME socket, so if any process used or closed it the others
        // would see "MySQL server has gone away". With the socket closed up front, the parent and every
        // worker reconnect lazily, each on its own independent connection.
        $this->connection->close();

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
                exit($this->runWorker($worker, $startBarrier, $work));
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

        $cycles = $this->raisedDeadlockCount() - $deadlocksBefore;

        static::assertSame([], $errored, 'Some workers failed with a non-deadlock error: ' . $this->readWorkerErrors($errored));
        static::assertSame(
            [],
            $deadlocked,
            \sprintf('A deadlock (SQLSTATE 40001) escaped the retry budget in %d of %d workers that %s.', \count($deadlocked), self::WORKERS, $what),
        );
        static::assertSame(
            0,
            $cycles,
            \sprintf('%d InnoDB deadlock cycles (error 1213) were raised while %d workers %s.', $cycles, self::WORKERS, $what),
        );
    }

    /**
     * @param \Closure(int): void $work
     */
    private function runWorker(int $worker, string $startBarrier, \Closure $work): int
    {
        // vary the per-worker randomness so workers walk the shared rows in different orders
        mt_srand(1000 + $worker);

        // block until the parent releases all workers at once
        $deadline = microtime(true) + 5.0;
        while (!is_file($startBarrier) && microtime(true) < $deadline) {
            usleep(1000);
        }

        try {
            $work($worker);
        } catch (\Throwable $e) {
            if ($this->isDeadlock($e)) {
                return self::EXIT_DEADLOCK;
            }

            file_put_contents($this->workerLogPath($worker), $e::class . ': ' . $e->getMessage());

            return self::EXIT_OTHER_ERROR;
        }

        return 0;
    }

    private function generateSeoUrls(int $worker): void
    {
        $context = Context::createDefaultContext();
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId($this->salesChannelId);

        for ($round = 0; $round < self::ROUNDS; ++$round) {
            // Every worker updates the same shared set of foreign keys, but each in a different order and
            // toggling every url to a fresh path each round (mimicking concurrent category reparenting,
            // which rewrites the seo path). Each foreign key owns a private pair of paths, so there is no
            // cross-row data conflict - only the lock ordering differs.
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

            $this->seoUrlPersister->updateSeoUrls($context, self::ROUTE_NAME, $foreignKeys, $seoUrls, $salesChannel);
        }
    }

    private function recalculateChildCountsWhileReparenting(int $worker): void
    {
        $context = Context::createDefaultContext();
        $version = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);

        // half the workers recompute the child counts, the other half reparent children underneath the
        // same parents. That is the contention the self-joined UPDATE deadlocks on: it holds shared locks
        // on the child rows while taking exclusive locks on the parents, racing the child writes.
        $recompute = $worker % 2 === 0;

        $children = $this->childCategoryIds;
        $parents = $this->parentCategoryIds;
        if ($children === [] || $parents === []) {
            return;
        }

        for ($round = 0; $round < self::ROUNDS; ++$round) {
            if ($recompute) {
                $this->childCountUpdater->update(CategoryDefinition::ENTITY_NAME, $parents, $context);

                continue;
            }

            $child = $children[array_rand($children)];
            $parent = $parents[array_rand($parents)];
            $this->connection->executeStatement(
                'UPDATE category SET parent_id = :parent WHERE id = :id AND version_id = :version',
                [
                    'parent' => Uuid::fromHexToBytes($parent),
                    'id' => Uuid::fromHexToBytes($child),
                    'version' => $version,
                ],
            );
        }
    }

    /**
     * Total number of InnoDB deadlock cycles (error 1213) the server has reported since startup, read
     * from performance_schema. This counts every raised deadlock, including those the retry loop recovered
     * from, so the assertion catches the deadlocks even when the retries keep them from reaching the caller.
     */
    private function raisedDeadlockCount(): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT SUM_ERROR_RAISED FROM performance_schema.events_errors_summary_global_by_error WHERE ERROR_NUMBER = 1213'
        );
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
        return sys_get_temp_dir() . '/category_indexing_repro_worker_' . $worker . '.log';
    }

    private function startBarrierPath(): string
    {
        return sys_get_temp_dir() . '/category_indexing_repro_start_barrier';
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

    private function seedSeoUrlPool(): void
    {
        $languageId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $now = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        for ($i = 0; $i < self::SEO_POOL; ++$i) {
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

    private function seedCategoryTree(): void
    {
        $version = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);
        $now = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        for ($i = 0; $i < self::CATEGORY_PARENTS; ++$i) {
            $id = Uuid::randomHex();
            $this->parentCategoryIds[] = $id;
            $this->connection->insert('category', [
                'id' => Uuid::fromHexToBytes($id),
                'version_id' => $version,
                'type' => 'page',
                'created_at' => $now,
            ]);
        }

        for ($i = 0; $i < self::CATEGORY_CHILDREN; ++$i) {
            $id = Uuid::randomHex();
            $this->childCategoryIds[] = $id;
            $this->connection->insert('category', [
                'id' => Uuid::fromHexToBytes($id),
                'version_id' => $version,
                'parent_id' => Uuid::fromHexToBytes($this->parentCategoryIds[$i % self::CATEGORY_PARENTS]),
                'type' => 'page',
                'created_at' => $now,
            ]);
        }
    }

    private function cleanupReproRows(): void
    {
        $this->connection->executeStatement('DELETE FROM seo_url WHERE route_name = :route', ['route' => self::ROUTE_NAME]);

        $categoryIds = array_map(
            static fn (string $hex): string => Uuid::fromHexToBytes($hex),
            [...$this->parentCategoryIds, ...$this->childCategoryIds],
        );

        if ($categoryIds !== []) {
            // break the self-referential parent_id link before deleting so the FK does not block the delete
            $this->connection->executeStatement(
                'UPDATE category SET parent_id = NULL WHERE id IN (:ids)',
                ['ids' => $categoryIds],
                ['ids' => ArrayParameterType::BINARY],
            );
            $this->connection->executeStatement(
                'DELETE FROM category WHERE id IN (:ids)',
                ['ids' => $categoryIds],
                ['ids' => ArrayParameterType::BINARY],
            );
        }
    }
}
