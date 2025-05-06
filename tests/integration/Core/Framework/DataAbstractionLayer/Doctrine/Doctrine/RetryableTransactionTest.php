<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Doctrine\Doctrine;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableTransaction;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(RetryableTransaction::class)]
class RetryableTransactionTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->getContainer()->get(Connection::class);
    }

    public function testRetryableTransactionRealDeadlock(): void
    {
        // Insert two rows that we'll use to create a deadlock
        $id1 = '00000000000000000000000000000001';
        $id2 = '00000000000000000000000000000002';
        $this->connection->executeStatement(
            'REPLACE INTO tag (id, name, created_at) VALUES
                (UNHEX(:id1), :name1, NOW()),
                (UNHEX(:id2), :name2, NOW())',
            ['id1' => $id1, 'name1' => 'tag1', 'id2' => $id2, 'name2' => 'tag2']
        );

        // Create a separate connection for the helper process
        $params = $this->connection->getParams();
        $helper = new RetryableTransactionDeadlockHelper($params, $id1, $id2);

        // Start the main transaction
        $this->connection->beginTransaction();

        // Lock row 1 in the main connection
        $this->connection->executeStatement(
            'SELECT * FROM tag WHERE id = UNHEX(:id) FOR UPDATE',
            ['id' => $id1]
        );

        // Start the helper process in a separate thread
        $pid = pcntl_fork();
        if ($pid === -1) {
            throw new \RuntimeException('Could not fork process');
        }

        if ($pid === 0) {
            // Child process
            $helper->execute();
            exit(0);
        }

        usleep(50000); // 50ms to ensure helper has started

        // Now create a deadlock by having the main process try to lock row 2
        $counter = 0;
        $result = RetryableTransaction::retryable($this->connection, function () use ($id2, &$counter) {
            $counter++;

            // Add a small delay to ensure helper process has time to attempt locking row 1
            usleep(150000); // 150ms

            // This will try to lock row 2 while holding a lock on row 1
            $this->connection->executeStatement(
                'SELECT * FROM tag WHERE id = UNHEX(:id) FOR UPDATE',
                ['id' => $id2]
            );

            return $counter;
        });

        // Wait for the helper process to complete
        pcntl_waitpid($pid, $status);

        // Clean up: rollback transaction and delete test data
        $this->connection->rollBack();
        $this->connection->executeStatement('DELETE FROM tag WHERE id IN (UNHEX(:id1), UNHEX(:id2))', ['id1' => $id1, 'id2' => $id2]);

        // Verify that the operation was retried at least once, indicating a deadlock was detected and handled
        static::assertGreaterThan(1, $result, 'Operation should have been retried at least once due to deadlock');
    }

//    public function testRetryableTransactionRetriesOnDeadlock(): void
//    {
//        $counter = 0;
//        $f = function () use (&$counter) {
//            $counter++;
//            throw new DeadlockException(
//                new Exception('Deadlock detected'),
//                null,
//            );
//        };
//
//        $e = null;
//        try {
//            RetryableTransaction::retryable($this->connection, $f);
//        } catch (RetryableException $e) {
//        }
//
//        static::assertEquals(11, $counter);
//        static::assertInstanceOf(RetryableException::class, $e);
//    }

}
