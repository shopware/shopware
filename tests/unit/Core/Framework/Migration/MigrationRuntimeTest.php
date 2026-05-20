<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Migration\MigrationRuntime;
use Shopware\Core\Framework\Migration\MigrationSource;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[CoversClass(MigrationRuntime::class)]
class MigrationRuntimeTest extends TestCase
{
    protected function setUp(): void
    {
        SuccessAfterFkGuardErrorMigration::$updateCalls = 0;
        AlwaysFailingMigration::$updateCalls = 0;
        AlwaysFailingFkGuardMigration::$updateCalls = 0;
    }

    public function testMigrateRetriesOnceWhenMysql84FkGuardErrorIsThrown(): void
    {
        $connection = $this->createConnectionExecutingMigration(SuccessAfterFkGuardErrorMigration::class);

        $sets = [];
        $connection->method('executeStatement')
            ->willReturnCallback(function (string $sql) use (&$sets): int {
                if (str_starts_with($sql, 'UPDATE `migration`')) {
                    return 1;
                }
                $sets[] = $sql;

                return 0;
            });

        $connection->method('fetchOne')->willReturn(1);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(static::stringContains('118151'));

        $runtime = new MigrationRuntime($connection, $logger);
        iterator_to_array($runtime->migrate($this->source()));

        static::assertSame(2, SuccessAfterFkGuardErrorMigration::$updateCalls, 'update() should be called twice (initial + retry)');
        static::assertContains('SET SESSION restrict_fk_on_non_standard_key = OFF', $sets);
        static::assertContains('SET SESSION restrict_fk_on_non_standard_key = 1', $sets);
    }

    public function testMigratePropagatesUnrelatedExceptionWithoutRetrying(): void
    {
        $connection = $this->createConnectionExecutingMigration(AlwaysFailingMigration::class);
        $connection->method('executeStatement')->willReturn(0);
        $connection->method('fetchOne')->willReturn(1);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');
        $logger->expects($this->atLeastOnce())->method('error');

        $runtime = new MigrationRuntime($connection, $logger);

        try {
            iterator_to_array($runtime->migrate($this->source()));
            static::fail('Expected RuntimeException to propagate');
        } catch (\RuntimeException $e) {
            static::assertSame('unrelated failure', $e->getMessage());
        }

        static::assertSame(1, AlwaysFailingMigration::$updateCalls, 'update() must not be retried for unrelated errors');
    }

    public function testMigrateThrowsWhenRetryAlsoFails(): void
    {
        $connection = $this->createConnectionExecutingMigration(AlwaysFailingFkGuardMigration::class);
        $connection->method('executeStatement')->willReturn(0);
        $connection->method('fetchOne')->willReturn(1);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');
        $logger->expects($this->atLeastOnce())->method('error');

        $runtime = new MigrationRuntime($connection, $logger);

        $caught = null;
        try {
            iterator_to_array($runtime->migrate($this->source()));
        } catch (\Throwable $e) {
            $caught = $e;
        }

        static::assertNotNull($caught);
        static::assertMatchesRegularExpression('/Cannot drop index .*<unknown key name>/', $caught->getMessage());
        static::assertSame(2, AlwaysFailingFkGuardMigration::$updateCalls, 'update() should be called twice before the second failure propagates');
    }

    public function testMigrateRetryHandlesUnsupportedGuardVariableOnMariadb(): void
    {
        // On MariaDB / MySQL <8.4 the session variable does not exist; the
        // SELECT inside disableNonStandardFkGuard() throws, the method
        // returns null, the retry runs anyway, and the matching restore
        // call no-ops. This test covers both fallback branches.
        $connection = $this->createConnectionExecutingMigration(AlwaysFailingFkGuardMigration::class);
        $connection->method('executeStatement')->willReturn(0);
        $connection->method('fetchOne')->willThrowException(new \RuntimeException('Unknown system variable'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');
        $logger->expects($this->atLeastOnce())->method('error');

        $runtime = new MigrationRuntime($connection, $logger);

        $caught = null;
        try {
            iterator_to_array($runtime->migrate($this->source()));
        } catch (\Throwable $e) {
            $caught = $e;
        }

        static::assertNotNull($caught);
        static::assertSame(2, AlwaysFailingFkGuardMigration::$updateCalls, 'retry should still attempt update() even when the guard variable is unsupported');
    }

    public static function makeFkGuardException(): \Throwable
    {
        // Mirrors the wording the runtime's looksLikeNonStandardFkGuardBug
        // matches on. The runtime catches \Throwable, so the exception class
        // is irrelevant — only the message matters.
        return new \RuntimeException(
            'An exception occurred while executing a query: SQLSTATE[HY000]: '
            . 'General error: 1553 Cannot drop index \'<unknown key name>\': '
            . 'needed in a foreign key constraint'
        );
    }

    /**
     * @return Connection&MockObject
     */
    private function createConnectionExecutingMigration(string $migrationClass): Connection
    {
        $connection = $this->createMock(Connection::class);

        $result = $this->createMock(Result::class);
        $result->method('fetchFirstColumn')->willReturn([$migrationClass]);

        $queryBuilder = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['select', 'from', 'where', 'andWhere', 'orderBy', 'setParameter', 'setMaxResults', 'executeQuery'])
            ->getMock();
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('orderBy')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('setMaxResults')->willReturnSelf();
        $queryBuilder->method('executeQuery')->willReturn($result);
        $connection->method('createQueryBuilder')->willReturn($queryBuilder);

        $connection->method('update')->willReturn(1);

        return $connection;
    }

    private function source(): MigrationSource
    {
        return new MigrationSource('test', ['Shopware\\Tests\\Unit\\Core\\Framework\\Migration']);
    }
}

/**
 * @internal
 */
final class SuccessAfterFkGuardErrorMigration extends MigrationStep
{
    public static int $updateCalls = 0;

    public function getCreationTimestamp(): int
    {
        return 1700000000;
    }

    public function update(Connection $connection): void
    {
        ++self::$updateCalls;

        if (self::$updateCalls === 1) {
            throw MigrationRuntimeTest::makeFkGuardException();
        }
    }
}

/**
 * @internal
 */
final class AlwaysFailingMigration extends MigrationStep
{
    public static int $updateCalls = 0;

    public function getCreationTimestamp(): int
    {
        return 1700000001;
    }

    public function update(Connection $connection): void
    {
        ++self::$updateCalls;

        throw new \RuntimeException('unrelated failure');
    }
}

/**
 * @internal
 */
final class AlwaysFailingFkGuardMigration extends MigrationStep
{
    public static int $updateCalls = 0;

    public function getCreationTimestamp(): int
    {
        return 1700000002;
    }

    public function update(Connection $connection): void
    {
        ++self::$updateCalls;

        throw MigrationRuntimeTest::makeFkGuardException();
    }
}
