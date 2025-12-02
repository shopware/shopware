<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Doctrine\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDO\Exception;
use Doctrine\DBAL\Exception\DeadlockException;
use Doctrine\DBAL\Exception\RetryableException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableTransaction;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(RetryableTransaction::class)]
class RetryableTransactionTest extends TestCase
{
    public function testRetryableTransactionRetriesOnDeadlock(): void
    {
        $counter = 0;
        $f = function () use (&$counter): void {
            ++$counter;
            throw new DeadlockException(
                new Exception('Deadlock detected'),
                null,
            );
        };

        $connection = $this->createMock(Connection::class);
        $connection->method('getTransactionNestingLevel')->willReturn(0);
        $connection->method('transactional')->willReturnCallback($f);

        $e = null;
        try {
            RetryableTransaction::retryable($connection, $f);
            /** @phpstan-ignore catch.neverThrown (exceptions are thrown in passed closure) */
        } catch (\Throwable $e) {
        }

        static::assertInstanceOf(RetryableException::class, $e);
        static::assertSame(11, $counter);
    }
}
