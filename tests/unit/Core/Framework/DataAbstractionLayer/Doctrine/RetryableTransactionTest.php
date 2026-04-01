<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDO\Exception;
use Doctrine\DBAL\Exception\DeadlockException;
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
        $this->expectException(DeadlockException::class);

        $counter = 0;
        $f = static function () use (&$counter): void {
            ++$counter;
            throw new DeadlockException(
                new Exception('Deadlock detected'),
                null,
            );
        };

        $connection = $this->createMock(Connection::class);
        $connection->method('getTransactionNestingLevel')->willReturn(0);
        $connection->method('transactional')->willReturnCallback($f);

        try {
            RetryableTransaction::retryable($connection, $f);
        } finally {
            static::assertSame(11, $counter);
        }
    }
}
