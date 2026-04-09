<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\System\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\System\Command\SystemRestoreDatabaseCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * @internal
 *
 * @phpstan-import-type Params from DriverManager
 */
#[CoversClass(SystemRestoreDatabaseCommand::class)]
class SystemRestoreDatabaseCommandTest extends TestCase
{
    private const RESTORE_DIR = '/tmp/sw-restore-test';
    private const DB_NAME = 'shopware';
    private const DB_PARAMS = ['user' => 'root', 'host' => 'localhost', 'port' => 3306];

    private Connection&MockObject $connection;

    private Filesystem&MockObject $filesystem;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->connection->method('getDatabase')->willReturn(self::DB_NAME);
        $this->connection->method('getParams')->willReturn(self::DB_PARAMS);

        $this->filesystem = $this->createMock(Filesystem::class);
    }

    /**
     * @param array<string, int|string> $connectionParams
     * @param array<string> $expectedCmdParts
     */
    #[DataProvider('executeRestoreProvider')]
    public function testExecuteBuildsCorrectCommand(
        $connectionParams,
        string $dbName,
        $expectedCmdParts,
    ): void {
        $capturedCommands = [];

        $restoreProcess = $this->createMock(Process::class);
        $restoreProcess->method('run')->willReturn(0);
        $restoreProcess->method('getExitCode')->willReturn(0);
        $restoreProcess->expects($this->once())->method('setInput');

        $processFactory = static function (array $cmd) use (&$capturedCommands, $restoreProcess): Process {
            $capturedCommands[] = $cmd;

            return $restoreProcess;
        };

        $this->connection = $this->createMock(Connection::class);
        $this->connection->method('getDatabase')->willReturn($dbName);
        $this->connection->method('getParams')->willReturn($connectionParams);

        $this->filesystem->expects($this->once())
            ->method('mkdir')
            ->with(self::RESTORE_DIR);

        $tester = new CommandTester(new SystemRestoreDatabaseCommand(self::RESTORE_DIR, $this->connection, $processFactory, $this->filesystem));
        $tester->execute([]);

        static::assertSame(Command::SUCCESS, $tester->getStatusCode());
        $restoreCmd = $capturedCommands[0];
        foreach ($expectedCmdParts as $part) {
            static::assertContains($part, $restoreCmd);
        }
    }

    /**
     * @return \Generator<string, array{connectionParams: array<string, int|string>, dbName: string, expectedCmdParts: list<string>}>
     */
    public static function executeRestoreProvider(): \Generator
    {
        yield 'basic connection without password' => [
            'connectionParams' => ['user' => 'root', 'host' => 'localhost', 'port' => 3306],
            'dbName' => 'shopware',
            'expectedCmdParts' => ['mysql', '-u', 'root', '-h', 'localhost', '--port=3306', 'shopware'],
        ];

        yield 'connection with password' => [
            'connectionParams' => ['user' => 'root', 'host' => 'localhost', 'port' => 3306, 'password' => 's3cr3t'],
            'dbName' => 'shopware',
            'expectedCmdParts' => ['-ps3cr3t', 'shopware'],
        ];
    }

    public function testExecuteReturnsErrorCodeOnFailure(): void
    {
        $restoreProcess = $this->createMock(Process::class);
        $restoreProcess->method('run')->willReturn(1);
        $restoreProcess->method('getExitCode')->willReturn(1);
        $restoreProcess->expects($this->once())->method('setInput');

        $processFactory = static fn (array $cmd): Process => $restoreProcess;

        $this->filesystem->expects($this->once())
            ->method('mkdir')
            ->with(self::RESTORE_DIR);

        $command = new SystemRestoreDatabaseCommand(
            self::RESTORE_DIR,
            $this->connection,
            $processFactory,
            $this->filesystem,
        );

        $tester = new CommandTester($command);
        $tester->execute([]);

        static::assertSame(1, $tester->getStatusCode());
    }

    public function testReadsSqlContentIfFileExists(): void
    {
        $restoreProcess = $this->createMock(Process::class);
        $restoreProcess->method('run')->willReturn(0);
        $restoreProcess->method('getExitCode')->willReturn(0);
        $restoreProcess->expects($this->once())
            ->method('setInput')
            ->with('SOME SQL');

        $processFactory = static fn (array $cmd): Process => $restoreProcess;

        $this->filesystem->expects($this->once())
            ->method('mkdir')
            ->with(self::RESTORE_DIR);
        $this->filesystem->method('exists')->willReturn(true);
        $this->filesystem->method('readFile')->willReturn('SOME SQL');

        $command = new SystemRestoreDatabaseCommand(
            self::RESTORE_DIR,
            $this->connection,
            $processFactory,
            $this->filesystem,
        );

        $tester = new CommandTester($command);
        $tester->execute([]);

        static::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }
}
