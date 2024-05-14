<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Installer\Database;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Migration\MigrationCollection;
use Shopware\Core\Framework\Migration\MigrationCollectionLoader;
use Shopware\Core\Installer\Database\DatabaseMigrator;
use Shopware\Core\Installer\Database\MigrationCollectionFactory;
use Shopware\Core\Kernel;
use Shopware\Core\Maintenance\System\Service\SetupDatabaseAdapter;

/**
 * @internal
 */
#[CoversClass(DatabaseMigrator::class)]
class DatabaseMigratorTest extends TestCase
{
    private MockObject&SetupDatabaseAdapter $setupAdapter;

    private Connection&MockObject $connection;

    private MockObject&MigrationCollection $migrationCollection;

    private DatabaseMigrator $databaseMigrator;

    protected function setUp(): void
    {
        $this->setupAdapter = $this->createMock(SetupDatabaseAdapter::class);

        $this->connection = $this->createMock(Connection::class);

        $this->migrationCollection = $this->createMock(MigrationCollection::class);

        $migrationLoader = $this->createMock(MigrationCollectionLoader::class);
        $migrationLoader->method('collectAllForVersion')
            ->with(Kernel::SHOPWARE_FALLBACK_VERSION)
            ->willReturn($this->migrationCollection);

        $migrationCollectorFactory = $this->createMock(MigrationCollectionFactory::class);
        $migrationCollectorFactory
            ->method('getMigrationCollectionLoader')
            ->with($this->connection)
            ->willReturn($migrationLoader);

        $this->databaseMigrator = new DatabaseMigrator(
            $this->setupAdapter,
            $migrationCollectorFactory,
            Kernel::SHOPWARE_FALLBACK_VERSION
        );
    }

    public function testInitialMigrate(): void
    {
        $this->setupAdapter->expects(static::once())
            ->method('initializeShopwareDb')
            ->with($this->connection);

        $this->migrationCollection->expects(static::once())
            ->method('sync');

        $this->migrationCollection->expects(static::once())
            ->method('migrateInSteps')
            ->with(null, 1)
            ->willReturnCallback(fn () => yield 'migration');

        $this->migrationCollection->expects(static::never())
            ->method('migrateDestructiveInSteps');

        $this->migrationCollection->expects(static::once())
            ->method('getTotalMigrationCount')
            ->willReturn(5);

        $this->migrationCollection->expects(static::once())
            ->method('getExecutableDestructiveMigrations')
            ->willReturn(['migration']);

        \ini_set('max_execution_time', '10');

        $result = $this->databaseMigrator->migrate(0, $this->connection);

        \ini_restore('max_execution_time');

        static::assertSame([
            'offset' => 1,
            'total' => 10,
            'isFinished' => false,
        ], $result);
    }

    public function testUnfinishedMigration(): void
    {
        $this->setupAdapter->expects(static::never())
            ->method('initializeShopwareDb')
            ->with($this->connection);

        $this->migrationCollection->expects(static::never())
            ->method('sync');

        $this->migrationCollection->expects(static::exactly(2))
            ->method('migrateInSteps')
            ->with(null, 1)
            ->willReturnOnConsecutiveCalls(
                $this->nonEmptyGenerator(),
                $this->emptyGenerator(),
            );

        $this->migrationCollection->expects(static::exactly(2))
            ->method('migrateDestructiveInSteps')
            ->with(null, 1)
            ->willReturnOnConsecutiveCalls(
                $this->nonEmptyGenerator(),
                $this->emptyGenerator(),
            );

        $this->migrationCollection->expects(static::once())
            ->method('getTotalMigrationCount')
            ->willReturn(5);

        $this->migrationCollection->expects(static::once())
            ->method('getExecutableDestructiveMigrations')
            ->willReturn(['migration']);

        \ini_set('max_execution_time', '10');

        $result = $this->databaseMigrator->migrate(1, $this->connection);

        \ini_restore('max_execution_time');

        static::assertSame([
            'offset' => 3,
            'total' => 10,
            'isFinished' => false,
        ], $result);
    }

    public function testFinishedMigration(): void
    {
        $this->setupAdapter->expects(static::never())
            ->method('initializeShopwareDb')
            ->with($this->connection);

        $this->migrationCollection->expects(static::never())
            ->method('sync');

        $this->migrationCollection->expects(static::exactly(3))
            ->method('migrateInSteps')
            ->with(null, 1)
            ->willReturnOnConsecutiveCalls(
                $this->nonEmptyGenerator(),
                $this->nonEmptyGenerator(),
                $this->emptyGenerator(),
            );

        $this->migrationCollection->expects(static::exactly(3))
            ->method('migrateDestructiveInSteps')
            ->with(null, 1)
            ->willReturnOnConsecutiveCalls(
                $this->nonEmptyGenerator(),
                $this->nonEmptyGenerator(),
                $this->emptyGenerator(),
            );

        $this->migrationCollection->expects(static::once())
            ->method('getTotalMigrationCount')
            ->willReturn(5);

        $this->migrationCollection->expects(static::once())
            ->method('getExecutableDestructiveMigrations')
            ->willReturn([]);

        \ini_set('max_execution_time', '10');

        $result = $this->databaseMigrator->migrate(6, $this->connection);

        \ini_restore('max_execution_time');

        static::assertSame([
            'offset' => 10,
            'total' => 10,
            'isFinished' => true,
        ], $result);
    }

    private function nonEmptyGenerator(): \Generator
    {
        yield 'migration';
    }

    private function emptyGenerator(): \Generator
    {
        yield from [];
    }
}
