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
use Shopware\Tests\Unit\Core\Framework\Migration\_fixtures\MigrationRuntime\TestableMigrationRuntime;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(MigrationRuntime::class)]
class MigrationRuntimeTest extends TestCase
{
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

    private function createSource(): MigrationSource
    {
        return new MigrationSource('test', [__DIR__ => 'Shopware\Tests\Unit\Core\Framework\Migration\_fixtures\MigrationRuntime']);
    }
}
