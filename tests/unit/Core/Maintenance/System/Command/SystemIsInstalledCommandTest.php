<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Maintenance\System\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Maintenance\System\Command\SystemIsInstalledCommand;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[CoversClass(SystemIsInstalledCommand::class)]
class SystemIsInstalledCommandTest extends TestCase
{
    /**
     * @deprecated tag:v6.8.0
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testInstalled(): void
    {
        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager->expects($this->once())->method('tableExists')->with('migration')->willReturn(true);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('createSchemaManager')->willReturn($schemaManager);

        $command = new SystemIsInstalledCommand($connection);
        $tester = new CommandTester($command);

        static::assertSame(Command::SUCCESS, $tester->execute([]));
    }

    /**
     * @deprecated tag:v6.8.0
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testNotInstalled(): void
    {
        $schemaManager = $this->createMock(AbstractSchemaManager::class);
        $schemaManager->expects($this->once())->method('tableExists')->with('migration')->willReturn(false);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('createSchemaManager')->willReturn($schemaManager);

        $command = new SystemIsInstalledCommand($connection);
        $tester = new CommandTester($command);

        static::assertSame(Command::FAILURE, $tester->execute([]));
    }
}
