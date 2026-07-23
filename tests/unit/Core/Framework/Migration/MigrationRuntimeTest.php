<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Migration;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationException;
use Shopware\Core\Framework\Migration\MigrationRuntime;
use Shopware\Core\Framework\Migration\MigrationSource;
use Shopware\Tests\Unit\Core\Framework\Migration\_fixtures\MigrationRuntime\Migration1000000001Successful;
use Shopware\Tests\Unit\Core\Framework\Migration\_fixtures\MigrationRuntime\Migration1000000002Failing;
use Shopware\Tests\Unit\Core\Framework\Migration\_fixtures\MigrationRuntime\Migration1000000003FkFailing;
use Shopware\Tests\Unit\Core\Framework\Migration\_fixtures\MigrationRuntime\Migration1000000004FkGuardFailingOnce;
use Shopware\Tests\Unit\Core\Framework\Migration\_fixtures\MigrationRuntime\Migration1000000005FkGuardAlwaysFailing;
use Shopware\Tests\Unit\Core\Framework\Migration\_fixtures\MigrationRuntime\TestableMigrationRuntime;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(MigrationRuntime::class)]
class MigrationRuntimeTest extends TestCase
{
    protected function setUp(): void
    {
        Migration1000000004FkGuardFailingOnce::$updateCalls = 0;
        Migration1000000005FkGuardAlwaysFailing::$updateCalls = 0;
    }

    #[TestDox('migrate executes the steps in order and marks each as executed')]
    public function testMigrateExecutesAndMarksSteps(): void
    {
        $statements = [];
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('executeStatement')
            ->willReturnCallback(static function (string $sql, array $params = []) use (&$statements): int {
                $statements[] = [$sql, $params];

                return 1;
            });

        $runtime = new TestableMigrationRuntime($connection, new NullLogger());
        $runtime->executableMigrations = [Migration1000000001Successful::class];

        $executed = iterator_to_array($runtime->migrate($this->createSource()));

        static::assertSame([Migration1000000001Successful::class], $executed);
        static::assertTrue($runtime->storageEngineSet);
        static::assertCount(1, $statements);
        static::assertStringContainsString('`update` = NOW(6)', $statements[0][0]);
        static::assertSame(['class' => Migration1000000001Successful::class], $statements[0][1]);
    }

    #[TestDox('migrateDestructive marks the destructive column instead')]
    public function testMigrateDestructiveMarksDestructiveColumn(): void
    {
        $statements = [];
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('executeStatement')
            ->willReturnCallback(static function (string $sql, array $params = []) use (&$statements): int {
                $statements[] = [$sql, $params];

                return 1;
            });

        $runtime = new TestableMigrationRuntime($connection, new NullLogger());
        $runtime->executableDestructiveMigrations = [Migration1000000001Successful::class];

        $executed = iterator_to_array($runtime->migrateDestructive($this->createSource()));

        static::assertSame([Migration1000000001Successful::class], $executed);
        static::assertStringContainsString('`update_destructive` = NOW(6)', $statements[0][0]);
        static::assertSame(['class' => Migration1000000001Successful::class], $statements[0][1]);
    }

    #[TestDox('migrate is lazy: nothing runs before the generator is iterated')]
    public function testMigrateIsLazy(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('executeStatement');

        $runtime = new TestableMigrationRuntime($connection, new NullLogger());
        $runtime->executableMigrations = [Migration1000000001Successful::class];

        $runtime->migrate($this->createSource());

        static::assertFalse($runtime->storageEngineSet);
    }

    #[TestDox('migrate skips a no-longer-existing migration class with a notice')]
    public function testMigrateSkipsMissingClass(): void
    {
        $notices = [];
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('notice')
            ->willReturnCallback(static function (string $message) use (&$notices): void {
                $notices[] = $message;
            });

        $runtime = new TestableMigrationRuntime(static::createStub(Connection::class), $logger);
        $runtime->executableMigrations = ['Shopware\Core\Migration\DoesNotExistAnymore'];

        static::assertSame([], iterator_to_array($runtime->migrate($this->createSource())));
        static::assertStringContainsString('DoesNotExistAnymore', $notices[0]);
    }

    #[TestDox('a failing migration is logged, its message persisted and the error rethrown')]
    public function testMigrateLogsAndRethrowsFailures(): void
    {
        $updates = [];
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('update')
            ->willReturnCallback(static function (string $table, array $data, array $criteria) use (&$updates): int {
                $updates[] = [$table, $data, $criteria];

                return 1;
            });

        $errors = [];
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->willReturnCallback(static function (string $message) use (&$errors): void {
                $errors[] = $message;
            });

        $runtime = new TestableMigrationRuntime($connection, $logger);
        $runtime->executableMigrations = [Migration1000000002Failing::class];

        try {
            iterator_to_array($runtime->migrate($this->createSource()));
            static::fail('expected the migration error to be rethrown');
        } catch (\RuntimeException $e) {
            static::assertSame(Migration1000000002Failing::ERROR_MESSAGE, $e->getMessage());
        }

        static::assertSame('migration', $updates[0][0]);
        static::assertSame(['`class`' => Migration1000000002Failing::class], $updates[0][2]);
        static::assertStringContainsString(Migration1000000002Failing::ERROR_MESSAGE, $errors[0]);
    }

    #[TestDox('a foreign-key violation is enriched with an actionable hint')]
    public function testMigrateEnrichesForeignKeyViolations(): void
    {
        $runtime = new TestableMigrationRuntime(static::createStub(Connection::class), new NullLogger());
        $runtime->executableMigrations = [Migration1000000003FkFailing::class];

        $this->expectException(MigrationException::class);
        $this->expectExceptionMessageMatches('/check the table `child_table` for entries that do not match the entries in table `parent_table`/');

        iterator_to_array($runtime->migrate($this->createSource()));
    }

    #[TestDox('the MySQL 8.4 FK-guard error triggers exactly one retry with the guard relaxed and restored')]
    public function testMigrateRetriesOnceWhenFkGuardBugIsHit(): void
    {
        $statements = [];
        $connection = $this->createMock(Connection::class);
        $connection->method('executeStatement')
            ->willReturnCallback(static function (string $sql) use (&$statements): int {
                $statements[] = $sql;

                return 1;
            });
        $connection->expects($this->once())
            ->method('fetchOne')
            ->with('SELECT @@SESSION.restrict_fk_on_non_standard_key')
            ->willReturn(1);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(static::stringContains('118151'));

        $runtime = new TestableMigrationRuntime($connection, $logger);
        $runtime->executableMigrations = [Migration1000000004FkGuardFailingOnce::class];

        $executed = iterator_to_array($runtime->migrate($this->createSource()));

        static::assertSame([Migration1000000004FkGuardFailingOnce::class], $executed);
        static::assertSame(2, Migration1000000004FkGuardFailingOnce::$updateCalls, 'update() should run twice: initial attempt + retry');
        static::assertContains('SET SESSION restrict_fk_on_non_standard_key = OFF', $statements);
        static::assertContains('SET SESSION restrict_fk_on_non_standard_key = 1', $statements);
    }

    #[TestDox('a retry that fails again propagates the FK-guard error after restoring the guard')]
    public function testMigrateRetryFailurePropagates(): void
    {
        $statements = [];
        $connection = static::createStub(Connection::class);
        $connection->method('executeStatement')
            ->willReturnCallback(static function (string $sql) use (&$statements): int {
                $statements[] = $sql;

                return 1;
            });
        $connection->method('fetchOne')->willReturn(1);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');
        $logger->expects($this->once())->method('error');

        $runtime = new TestableMigrationRuntime($connection, $logger);
        $runtime->executableMigrations = [Migration1000000005FkGuardAlwaysFailing::class];

        try {
            iterator_to_array($runtime->migrate($this->createSource()));
            static::fail('expected the FK-guard error to be rethrown after the failed retry');
        } catch (\RuntimeException $e) {
            static::assertSame(Migration1000000005FkGuardAlwaysFailing::ERROR_MESSAGE, $e->getMessage());
        }

        static::assertSame(2, Migration1000000005FkGuardAlwaysFailing::$updateCalls, 'update() should run twice before the second failure propagates');
        static::assertContains('SET SESSION restrict_fk_on_non_standard_key = 1', $statements, 'the guard must be restored even when the retry fails');
    }

    #[TestDox('unrelated migration errors are not retried and never touch the FK guard')]
    public function testMigrateDoesNotRetryUnrelatedErrors(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchOne');

        $runtime = new TestableMigrationRuntime($connection, new NullLogger());
        $runtime->executableMigrations = [Migration1000000002Failing::class];

        $this->expectExceptionObject(new \RuntimeException(Migration1000000002Failing::ERROR_MESSAGE));

        iterator_to_array($runtime->migrate($this->createSource()));
    }

    #[TestDox('the retry works without toggling the guard when the session variable is unsupported')]
    public function testMigrateRetriesWithoutGuardToggleWhenVariableUnsupported(): void
    {
        $statements = [];
        $connection = static::createStub(Connection::class);
        $connection->method('executeStatement')
            ->willReturnCallback(static function (string $sql) use (&$statements): int {
                $statements[] = $sql;

                return 1;
            });
        $connection->method('fetchOne')
            ->willThrowException(new \RuntimeException('Unknown system variable \'restrict_fk_on_non_standard_key\''));

        $runtime = new TestableMigrationRuntime($connection, new NullLogger());
        $runtime->executableMigrations = [Migration1000000004FkGuardFailingOnce::class];

        $executed = iterator_to_array($runtime->migrate($this->createSource()));

        static::assertSame([Migration1000000004FkGuardFailingOnce::class], $executed);
        static::assertSame(2, Migration1000000004FkGuardFailingOnce::$updateCalls);
        foreach ($statements as $statement) {
            static::assertStringNotContainsString('restrict_fk_on_non_standard_key', $statement);
        }
    }

    private function createSource(): MigrationSource
    {
        return new MigrationSource('test', [__DIR__ => 'Shopware\Tests\Unit\Core\Framework\Migration\_fixtures\MigrationRuntime']);
    }
}
