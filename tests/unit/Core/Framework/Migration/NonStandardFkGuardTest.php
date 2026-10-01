<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DriverException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\NonStandardFkGuard;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(NonStandardFkGuard::class)]
class NonStandardFkGuardTest extends TestCase
{
    private const DDL = 'ALTER TABLE `product` ADD COLUMN `foo` VARCHAR(32) NULL';

    private const ER_DROP_INDEX_FK = 1553;

    #[TestDox('a succeeding statement runs unmodified, without touching the guard')]
    public function testRunsStatementUnmodifiedWhenItSucceeds(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('executeStatement')->willReturnCallback(function (string $sql): int {
            static::assertSame(self::DDL, $sql);

            return 0;
        });
        $connection->expects($this->never())->method('fetchAssociative');

        NonStandardFkGuard::executeDdl($connection, self::DDL);
    }

    #[TestDox('failures other than error 1553 are rethrown without a retry')]
    public function testRethrowsOtherFailuresWithoutRetry(): void
    {
        $failure = new FakeDriverException(1091, 'Can\'t DROP INDEX `foo`; check that it exists');

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('executeStatement')->willThrowException($failure);
        $connection->expects($this->never())->method('fetchAssociative');

        $this->expectExceptionObject($failure);

        NonStandardFkGuard::executeDdl($connection, self::DDL);
    }

    /**
     * @param array<string, string>|false $guardState
     */
    #[TestDox('error 1553 is rethrown when the guard cannot explain the failure')]
    #[DataProvider('unexplainableGuardStates')]
    public function testRethrowsWhenGuardCannotExplainTheFailure(array|false $guardState): void
    {
        $failure = new FakeDriverException(self::ER_DROP_INDEX_FK, 'Cannot drop index \'idx\': needed in a foreign key constraint');

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('executeStatement')->willThrowException($failure);
        $connection->expects($this->once())->method('fetchAssociative')->willReturn($guardState);

        $this->expectExceptionObject($failure);

        NonStandardFkGuard::executeDdl($connection, self::DDL);
    }

    /**
     * @return \Generator<string, array{array<string, string>|false}>
     */
    public static function unexplainableGuardStates(): \Generator
    {
        yield 'variable absent (MariaDB, MySQL < 8.4)' => [false];
        yield 'guard already relaxed' => [['Variable_name' => 'restrict_fk_on_non_standard_key', 'Value' => 'OFF']];
    }

    #[TestDox('error 1553 with the guard ON relaxes the guard, retries, and restores the guard')]
    public function testRetriesWithRelaxedGuardAndRestoresIt(): void
    {
        $statements = [];

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->exactly(4))->method('executeStatement')
            ->willReturnCallback(function (string $sql) use (&$statements): int {
                $statements[] = $sql;

                if (\count($statements) === 1) {
                    throw new FakeDriverException(self::ER_DROP_INDEX_FK, 'Cannot drop index \'<unknown key name>\': needed in a foreign key constraint');
                }

                return 0;
            });
        $connection->expects($this->once())->method('fetchAssociative')
            ->willReturn(['Variable_name' => 'restrict_fk_on_non_standard_key', 'Value' => 'ON']);

        NonStandardFkGuard::executeDdl($connection, self::DDL);

        static::assertSame([
            self::DDL,
            'SET SESSION restrict_fk_on_non_standard_key = OFF',
            self::DDL,
            'SET SESSION restrict_fk_on_non_standard_key = ON',
        ], $statements);
    }

    #[TestDox('a failing retry surfaces its failure and still restores the guard')]
    public function testRestoresGuardWhenRetryFailsToo(): void
    {
        $retryFailure = new FakeDriverException(self::ER_DROP_INDEX_FK, 'Cannot drop index \'idx\': needed in a foreign key constraint');
        $statements = [];

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->exactly(4))->method('executeStatement')
            ->willReturnCallback(function (string $sql) use (&$statements, $retryFailure): int {
                $statements[] = $sql;

                if ($sql === self::DDL) {
                    throw \count($statements) === 1
                        ? new FakeDriverException(self::ER_DROP_INDEX_FK, 'Cannot drop index \'idx\': needed in a foreign key constraint')
                        : $retryFailure;
                }

                return 0;
            });
        $connection->expects($this->once())->method('fetchAssociative')
            ->willReturn(['Variable_name' => 'restrict_fk_on_non_standard_key', 'Value' => 'ON']);

        $thrown = null;

        try {
            NonStandardFkGuard::executeDdl($connection, self::DDL);
        } catch (DriverException $thrown) {
        }

        static::assertSame($retryFailure, $thrown);
        static::assertSame('SET SESSION restrict_fk_on_non_standard_key = ON', end($statements), 'The guard must be restored even when the retry fails');
    }
}

/**
 * Doctrine's DriverException can only be built from a live driver exception; bypass that.
 *
 * @internal
 */
class FakeDriverException extends DriverException
{
    public function __construct(
        int $errorCode,
        string $message
    ) {
        $this->code = $errorCode;
        $this->message = $message;
    }
}
