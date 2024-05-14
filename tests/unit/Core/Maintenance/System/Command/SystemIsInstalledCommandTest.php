<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Maintenance\System\Command;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Maintenance\System\Command\SystemIsInstalledCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[CoversClass(SystemIsInstalledCommand::class)]
class SystemIsInstalledCommandTest extends TestCase
{
    public function testInstalled(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(static::once())->method('fetchAllAssociative')->with('SHOW COLUMNS FROM migration');

        $command = new SystemIsInstalledCommand($connection);
        $tester = new CommandTester($command);

        static::assertSame(Command::SUCCESS, $tester->execute([]));
    }

    public function testNotInstalled(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(static::once())->method('fetchAllAssociative')->willThrowException(new \Exception('Not existing table'));

        $command = new SystemIsInstalledCommand($connection);
        $tester = new CommandTester($command);

        static::assertSame(Command::FAILURE, $tester->execute([]));
    }
}
