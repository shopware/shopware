<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Installer\Database;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Migration\MigrationCollection;
use Shopware\Core\Framework\Migration\MigrationCollectionLoader;
use Shopware\Core\Installer\Database\DatabaseMigrator;
use Shopware\Core\Installer\Database\MigrationCollectionFactory;
use Shopware\Core\Installer\Requirements\IniConfigReader;
use Shopware\Core\Kernel;
use Shopware\Core\Maintenance\System\Service\SetupDatabaseAdapter;
use Symfony\Component\Clock\NativeClock;

/**
 * @internal
 */
#[CoversClass(DatabaseMigrator::class)]
class DatabaseMigratorTest extends TestCase
{
    private MockObject&SetupDatabaseAdapter $setupAdapter;

    private Connection&MockObject $connection;

    private MockObject&MigrationCollection $migrationCollection;

    private MockObject&IniConfigReader $iniConfigReader;

    private string $maxExecutionTime = '10';

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

        $this->iniConfigReader = $this->createMock(IniConfigReader::class);
        $this->iniConfigReader
            ->method('get')
            ->with('max_execution_time')
            ->willReturnCallback(fn (): string => $this->maxExecutionTime);

        $this->databaseMigrator = new DatabaseMigrator(
            $this->setupAdapter,
            $migrationCollectorFactory,
            Kernel::SHOPWARE_FALLBACK_VERSION,
            $this->iniConfigReader,
            new NativeClock()
        );
    }

    public function testInitialMigrate(): void
    {
        $this->setupAdapter->expects($this->once())
            ->method('initializeShopwareDb')
            ->with($this->connection);

        $this->migrationCollection->expects($this->once())
            ->method('sync');

        $this->migrationCollection->expects($this->once())
            ->method('migrateInSteps')
            ->with(null, 1)
            ->willReturnCallback(static fn () => yield 'migration');

        $this->migrationCollection->expects($this->never())
            ->method('migrateDestructiveInSteps');

        $this->migrationCollection->expects($this->once())
            ->method('getTotalMigrationCount')
            ->willReturn(5);

        $this->migrationCollection->expects($this->once())
            ->method('getExecutableDestructiveMigrations')
            ->willReturn(['migration']);

        $result = $this->databaseMigrator->migrate(0, $this->connection);

        static::assertSame([
            'offset' => 1,
            'total' => 10,
            'isFinished' => false,
        ], $result);
    }

    public function testUnfinishedMigration(): void
    {
        $this->setupAdapter->expects($this->never())
            ->method('initializeShopwareDb')
            ->with($this->connection);

        $this->migrationCollection->expects($this->never())
            ->method('sync');

        $this->migrationCollection->expects($this->exactly(2))
            ->method('migrateInSteps')
            ->with(null, 1)
            ->willReturnOnConsecutiveCalls(
                $this->nonEmptyGenerator(),
                $this->emptyGenerator(),
            );

        $this->migrationCollection->expects($this->exactly(2))
            ->method('migrateDestructiveInSteps')
            ->with(null, 1)
            ->willReturnOnConsecutiveCalls(
                $this->nonEmptyGenerator(),
                $this->emptyGenerator(),
            );

        $this->migrationCollection->expects($this->once())
            ->method('getTotalMigrationCount')
            ->willReturn(5);

        $this->migrationCollection->expects($this->once())
            ->method('getExecutableDestructiveMigrations')
            ->willReturn(['migration']);

        $result = $this->databaseMigrator->migrate(1, $this->connection);

        static::assertSame([
            'offset' => 3,
            'total' => 10,
            'isFinished' => false,
        ], $result);
    }

    #[DataProvider('maxExecutionTimeProvider')]
    public function testFinishedMigration(string $maxExecutionTime): void
    {
        $this->maxExecutionTime = $maxExecutionTime;

        $this->setupAdapter->expects($this->never())
            ->method('initializeShopwareDb')
            ->with($this->connection);

        $this->migrationCollection->expects($this->never())
            ->method('sync');

        $this->migrationCollection->expects($this->exactly(3))
            ->method('migrateInSteps')
            ->with(null, 1)
            ->willReturnOnConsecutiveCalls(
                $this->nonEmptyGenerator(),
                $this->nonEmptyGenerator(),
                $this->emptyGenerator(),
            );

        $this->migrationCollection->expects($this->exactly(3))
            ->method('migrateDestructiveInSteps')
            ->with(null, 1)
            ->willReturnOnConsecutiveCalls(
                $this->nonEmptyGenerator(),
                $this->nonEmptyGenerator(),
                $this->emptyGenerator(),
            );

        $this->migrationCollection->expects($this->once())
            ->method('getTotalMigrationCount')
            ->willReturn(5);

        $this->migrationCollection->expects($this->once())
            ->method('getExecutableDestructiveMigrations')
            ->willReturn([]);

        $result = $this->databaseMigrator->migrate(6, $this->connection);

        static::assertSame([
            'offset' => 10,
            'total' => 10,
            'isFinished' => true,
        ], $result);
    }

    public static function maxExecutionTimeProvider(): \Generator
    {
        yield 'configured above installer cap' => ['10'];
        yield 'unlimited php runtime' => ['0'];
        yield 'unlimited php runtime from cli option' => ['-1'];
    }

    /**
     * @return \Generator<string>
     */
    private function nonEmptyGenerator(): \Generator
    {
        yield 'migration';
    }

    /**
     * @return \Generator<null>
     */
    private function emptyGenerator(): \Generator
    {
        yield from [];
    }
}
