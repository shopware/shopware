<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Migration\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\Command\RefreshMigrationCommand;
use Shopware\Core\Framework\Migration\MigrationException;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(RefreshMigrationCommand::class)]
class RefreshMigrationCommandTest extends TestCase
{
    private const MIGRATION_PATH = __DIR__ . '/_fixtures/Migration1772030791FooBar.php';
    private const OLD_TIMESTAMP = '1772030791';
    private const NEW_TIMESTAMP = '1783669827';
    private const MIGRATION_CONTENT = '<?php declare(strict_types=1);

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration%%TIMESTAMP%%FooBar extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return %%TIMESTAMP%%;
    }

    public function update(Connection $connection): void
    {
    }
}';

    public function testExecuteThrowsWhenClassNameDoesNotContainMigrationTimestamp(): void
    {
        $command = $this->createCommand();
        $commandTester = new CommandTester($command);

        $this->expectExceptionObject(MigrationException::couldNotDetermineTimestamp());
        $commandTester->execute(['path' => __DIR__ . '/_fixtures/InvalidMigration.php']);
    }

    public function testExecuteThrowsClassAtPathDoesNotExist(): void
    {
        $command = $this->createCommand();
        $commandTester = new CommandTester($command);

        $this->expectExceptionObject(MigrationException::migrationFileDoesNotExist(__DIR__ . '/_fixtures/DoesNotExist.php'));
        $commandTester->execute(['path' => __DIR__ . '/_fixtures/DoesNotExist.php']);
    }

    public function testExecute(): void
    {
        $mockedFilesystem = $this->createMock(Filesystem::class);
        $mockedFilesystem->expects($this->once())
            ->method('readFile')
            ->willReturn(str_replace('%%TIMESTAMP%%', self::OLD_TIMESTAMP, self::MIGRATION_CONTENT));

        $mockedFilesystem->expects($this->once())
            ->method('dumpFile')
            ->with(self::MIGRATION_PATH, str_replace('%%TIMESTAMP%%', self::NEW_TIMESTAMP, self::MIGRATION_CONTENT));

        $mockedFilesystem->expects($this->once())
            ->method('rename')
            ->with(self::MIGRATION_PATH, str_replace(self::OLD_TIMESTAMP, self::NEW_TIMESTAMP, self::MIGRATION_PATH));

        $mockDate = \DateTimeImmutable::createFromFormat('U', self::NEW_TIMESTAMP);
        static::assertNotFalse($mockDate);

        $command = new RefreshMigrationCommand(
            $mockedFilesystem,
            new MockClock($mockDate),
        );

        $result = (new CommandTester($command))->execute(['path' => self::MIGRATION_PATH]);
        static::assertSame(Command::SUCCESS, $result);
    }

    private function createCommand(): RefreshMigrationCommand
    {
        return new RefreshMigrationCommand(
            static::createStub(Filesystem::class),
            new MockClock(),
        );
    }
}
