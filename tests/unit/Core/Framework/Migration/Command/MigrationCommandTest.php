<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Migration\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\Command\MigrationCommand;
use Shopware\Core\Framework\Migration\MigrationCollection;
use Shopware\Core\Framework\Migration\MigrationCollectionLoader;
use Shopware\Core\Framework\Migration\MigrationException;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(MigrationCommand::class)]
class MigrationCommandTest extends TestCase
{
    private const SHOPWARE_VERSION = '6.5.2.0';

    public function testRequiresTimestampCapOrAllOption(): void
    {
        $command = new MigrationCommand(
            static::createStub(MigrationCollectionLoader::class),
            static::createStub(TagAwareAdapterInterface::class),
            self::SHOPWARE_VERSION,
        );

        $this->expectExceptionObject(MigrationException::invalidArgument('missing timestamp cap or --all option'));

        (new CommandTester($command))->execute([]);
    }

    public function testRejectsMultipleIdentifiersWithoutAllOption(): void
    {
        $command = new MigrationCommand(
            static::createStub(MigrationCollectionLoader::class),
            static::createStub(TagAwareAdapterInterface::class),
            self::SHOPWARE_VERSION,
        );

        $this->expectExceptionObject(MigrationException::invalidArgument('Running migrations for multiple identifiers without --all option or with --limit option is not supported.'));

        (new CommandTester($command))->execute(['identifier' => ['core', 'custom'], '--until' => '1000']);
    }

    #[TestDox('the core identifier migrates the version-scoped collection and clears the cache')]
    public function testCoreIdentifierMigratesAndClearsCache(): void
    {
        $collection = $this->createMock(MigrationCollection::class);
        $collection->expects($this->once())->method('sync');
        $collection->method('getExecutableMigrations')->willReturn(['MigrationA', 'MigrationB']);
        $collection->method('migrateInSteps')->willReturnCallback(static function (): \Generator {
            yield 'MigrationA';
            yield 'MigrationB';
        });

        $loader = $this->createMock(MigrationCollectionLoader::class);
        $loader->expects($this->once())
            ->method('collectAllForVersion')
            ->willReturnCallback(static function (string $version) use ($collection): MigrationCollection {
                static::assertSame(self::SHOPWARE_VERSION, $version);

                return $collection;
            });

        $cache = $this->createMock(TagAwareAdapterInterface::class);
        $cache->expects($this->once())->method('clear')->willReturn(true);

        $command = new MigrationCommand($loader, $cache, self::SHOPWARE_VERSION);
        $tester = new CommandTester($command);

        $tester->execute(['--all' => true]);

        $tester->assertCommandIsSuccessful();
        static::assertStringContainsString('2 out of 2', $tester->getDisplay());
    }

    #[TestDox('a custom identifier uses its own collection and skips the cache clear when nothing ran')]
    public function testCustomIdentifierWithoutMigrationsSkipsCacheClear(): void
    {
        $collection = $this->createMock(MigrationCollection::class);
        $collection->expects($this->once())->method('sync');
        $collection->method('getExecutableMigrations')->willReturn([]);
        $collection->method('migrateInSteps')->willReturnCallback(static function (): \Generator {
            yield from [];
        });

        $loader = $this->createMock(MigrationCollectionLoader::class);
        $loader->expects($this->once())
            ->method('collect')
            ->willReturnCallback(static function (string $identifier) use ($collection): MigrationCollection {
                static::assertSame('MyPlugin', $identifier);

                return $collection;
            });

        $cache = $this->createMock(TagAwareAdapterInterface::class);
        $cache->expects($this->never())->method('clear');

        $command = new MigrationCommand($loader, $cache, self::SHOPWARE_VERSION);
        $tester = new CommandTester($command);

        $tester->execute(['identifier' => ['MyPlugin'], '--all' => true]);

        $tester->assertCommandIsSuccessful();
    }

    #[TestDox('an unknown migration source is reported as a note and does not fail the command')]
    public function testUnknownSourceContinues(): void
    {
        $loader = static::createStub(MigrationCollectionLoader::class);
        $loader->method('collect')->willThrowException(MigrationException::unknownMigrationSource('MyPlugin'));

        $cache = $this->createMock(TagAwareAdapterInterface::class);
        $cache->expects($this->never())->method('clear');

        $command = new MigrationCommand($loader, $cache, self::SHOPWARE_VERSION);
        $tester = new CommandTester($command);

        $tester->execute(['identifier' => ['MyPlugin'], '--all' => true]);

        $tester->assertCommandIsSuccessful();
        static::assertStringContainsString('No collection found for identifier: "MyPlugin"', $tester->getDisplay());
    }

    #[TestDox('a failing migration is wrapped into a migration error with its trace')]
    public function testFailingMigrationIsWrapped(): void
    {
        $collection = $this->createMock(MigrationCollection::class);
        $collection->expects($this->once())->method('sync');
        $collection->method('getExecutableMigrations')->willReturn(['MigrationA']);
        $collection->method('migrateInSteps')->willReturnCallback(static function (): \Generator {
            yield from [];

            throw new \RuntimeException('kaboom');
        });

        $loader = static::createStub(MigrationCollectionLoader::class);
        $loader->method('collectAllForVersion')->willReturn($collection);

        $command = new MigrationCommand(
            $loader,
            static::createStub(TagAwareAdapterInterface::class),
            self::SHOPWARE_VERSION,
        );

        $this->expectException(MigrationException::class);
        $this->expectExceptionMessageMatches('/kaboom/');

        (new CommandTester($command))->execute(['--all' => true]);
    }
}
