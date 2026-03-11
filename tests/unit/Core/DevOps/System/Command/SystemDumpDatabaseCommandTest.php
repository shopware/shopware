<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\System\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\System\Command\SystemDumpDatabaseCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * @internal
 *
 * @phpstan-import-type Params from DriverManager
 */
#[CoversClass(SystemDumpDatabaseCommand::class)]
class SystemDumpDatabaseCommandTest extends TestCase
{
    private const DUMP_DIR = '/tmp/sw-dump-test';
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
     * @param Params $connectionParams
     * @param list<string> $ignoreTables
     * @param list<string> $expectedCmdParts
     */
    #[DataProvider('executeDumpProvider')]
    public function testExecuteBuildsCorrectCommand(
        array $connectionParams,
        string $dbName,
        array $ignoreTables,
        array $expectedCmdParts,
    ): void {
        $capturedCommands = [];

        $mkdirProcess = $this->createMock(Process::class);
        $mkdirProcess->method('mustRun')->willReturnSelf();

        $dumpProcess = $this->createMock(Process::class);
        $dumpProcess->method('mustRun')->willReturnSelf();
        $dumpProcess->method('getOutput')->willReturn('-- SQL dump content');

        $processFactory = static function (array $cmd) use (&$capturedCommands, $mkdirProcess, $dumpProcess): Process {
            $capturedCommands[] = $cmd;

            return $cmd[0] === 'mkdir' ? $mkdirProcess : $dumpProcess;
        };

        $this->connection = $this->createMock(Connection::class);
        $this->connection->method('getDatabase')->willReturn($dbName);
        $this->connection->method('getParams')->willReturn($connectionParams);

        $tester = new CommandTester(new SystemDumpDatabaseCommand(self::DUMP_DIR, $this->connection, $processFactory, $this->filesystem));
        $tester->execute(['--ignore-table' => $ignoreTables]);

        static::assertSame(Command::SUCCESS, $tester->getStatusCode());
        static::assertSame(['mkdir', '-p', self::DUMP_DIR], $capturedCommands[0]);

        $dumpCmd = $capturedCommands[1];
        foreach ($expectedCmdParts as $part) {
            static::assertContains($part, $dumpCmd);
        }
    }

    /**
     * @return \Generator<string, array{connectionParams: Params, dbName: string, ignoreTables: list<string>, expectedCmdParts: list<string>}>
     */
    public static function executeDumpProvider(): \Generator
    {
        yield 'basic connection without password' => [
            'connectionParams' => ['user' => 'root', 'host' => 'localhost', 'port' => 3306],
            'dbName' => 'shopware',
            'ignoreTables' => [],
            'expectedCmdParts' => ['mysqldump', '-u', 'root', '-h', 'localhost', '--port=3306', 'shopware'],
        ];

        yield 'connection with password' => [
            'connectionParams' => ['user' => 'root', 'host' => 'localhost', 'port' => 3306, 'password' => 's3cr3t'],
            'dbName' => 'shopware',
            'ignoreTables' => [],
            'expectedCmdParts' => ['mysqldump', '-ps3cr3t', 'shopware'],
        ];

        yield 'ignore tables are appended to command' => [
            'connectionParams' => ['user' => 'root', 'host' => 'localhost', 'port' => 3306],
            'dbName' => 'shopware',
            'ignoreTables' => ['enqueue', 'log_entry'],
            'expectedCmdParts' => ['--ignore-table=shopware.enqueue', '--ignore-table=shopware.log_entry'],
        ];
    }

    public function testDefaultProcessFactoryIsUsedWhenNotProvided(): void
    {
        $command = new SystemDumpDatabaseCommand(
            self::DUMP_DIR,
            $this->connection,
        );

        static::assertSame('system:dump', $command->getName());
    }

    public function testExecuteWritesPreambleAndAppendsDumpOutput(): void
    {
        $expectedPath = self::DUMP_DIR . '/localhost_' . self::DB_NAME . '.sql';
        $dumpOutput = '-- dump';

        $dumpProcess = $this->createMock(Process::class);
        $dumpProcess->method('mustRun')->willReturnSelf();
        $dumpProcess->method('getOutput')->willReturn($dumpOutput);

        $mkdirProcess = $this->createMock(Process::class);
        $mkdirProcess->method('mustRun')->willReturnSelf();

        $this->filesystem->expects($this->once())
            ->method('dumpFile')
            ->with($expectedPath, 'SET unique_checks=0;SET foreign_key_checks=0;');

        $this->filesystem->expects($this->once())
            ->method('appendToFile')
            ->with($expectedPath, $dumpOutput);

        $command = new SystemDumpDatabaseCommand(
            self::DUMP_DIR,
            $this->connection,
            static fn (array $cmd): Process => $cmd[0] === 'mkdir' ? $mkdirProcess : $dumpProcess,
            $this->filesystem,
        );

        $tester = new CommandTester($command);
        $tester->execute([]);

        static::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }
}
