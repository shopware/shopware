<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache\InvalidatorStorage;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\TransactionIsolationLevel;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\MultiInsertQueryQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Whilst this adapter attempts to work around various locking issues, it is not foolproof, so if you encounter deadlock due to load, use the `\Shopware\Core\Framework\Adapter\Cache\InvalidatorStorage\RedisInvalidatorStorage` adapter instead.
 *
 * @codeCoverageIgnore @see \Shopware\Tests\Integration\Core\Framework\Adapter\Cache\InvalidatorStorage\MySQLInvalidatorStorageTest
 */
#[Package('core')]
class MySQLInvalidatorStorage extends AbstractInvalidatorStorage
{
    private const TABLE_NAME = 'invalidation_tags';

    private readonly \Closure $debug;

    public function __construct(private readonly Connection $connection, private readonly LoggerInterface $logger, ?\Closure $debug = null)
    {
        $this->debug = $debug ?? (fn () => null)(...);
    }

    public function store(array $tags): void
    {
        if (empty($tags)) {
            return;
        }

        $insertQueue = new MultiInsertQueryQueue($this->connection, chunkSize: 1000, ignoreErrors: true);
        $insertQueue->addInserts(
            self::TABLE_NAME,
            array_map(
                fn (string $tag) => ['id' => Uuid::randomBytes(), 'tag' => $tag],
                array_values($tags)
            )
        );

        // we execute in read committed isolation so that row gap locks are not applied when inserting
        // this helps us prevent locks when trying to insert duplicate tags.
        $this->readCommittedIsolation(fn () => $insertQueue->execute());
    }

    /**
     * We attempt to read and lock all rows in the table. This works fine for subsequent executions, e.g. calling this method multiple times synchronously
     * however, if running in parallel, it can still be that the database returns similar result sets when it did not get a chance to lock the rows. Then, when trying to
     * delete, locks can be encountered if two processes attempt to delete the same row.
     */
    public function loadAndDelete(): array
    {
        try {
            return $this->readCommittedIsolation($this->executeLoadAndDelete(...));
        } catch (\Throwable $e) {
            $this->logger->warning('Cache tags could not be fetched or removed from storage. Possible deadlock encountered. If the error persists, try the redis adapter. Error: ' . $e->getMessage());

            return [];
        }
    }

    /**
     * @return list<string>
     */
    private function executeLoadAndDelete(): array
    {
        // fetch and lock records, ignoring locked records, se we don't handle tags
        // being processed by parallel worker
        $rows = $this->connection->fetchAllAssociative(
            \sprintf('SELECT id, tag FROM %s ORDER BY id FOR UPDATE SKIP LOCKED', self::TABLE_NAME)
        );

        ($this->debug)($this, $rows);

        if (empty($rows)) {
            return [];
        }

        $firstTagId = $rows[0]['id'];
        $lastTagId = $rows[array_key_last($rows)]['id'];

        $query = new RetryableQuery(
            $this->connection,
            $this->connection->prepare(\sprintf('DELETE FROM %s WHERE id BETWEEN ? AND ?', self::TABLE_NAME))
        );
        $query->execute([$firstTagId, $lastTagId]);

        return array_column($rows, 'tag');
    }

    /**
     * @param \Closure(Connection):T $callback
     *
     * @return T
     *
     * @template T
     */
    private function readCommittedIsolation(\Closure $callback): mixed
    {
        // used so that we don't lock the table for inserts
        $transactionIsolation = $this->connection->getTransactionIsolation();
        $this->connection->setTransactionIsolation(TransactionIsolationLevel::READ_COMMITTED);

        try {
            return $this->connection->transactional($callback);
        } finally {
            // restore original isolation mode
            $this->connection->setTransactionIsolation($transactionIsolation);
        }
    }
}
