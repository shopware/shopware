<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Command\DeleteAdminFilesAfterBuildCommand;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[CoversClass(DeleteAdminFilesAfterBuildCommand::class)]
class DeleteAdminFilesAfterBuildCommandTest extends TestCase
{
    private DeleteAdminFilesAfterBuildCommand $command;

    private Filesystem&MockObject $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = $this->createMock(Filesystem::class);
        $this->command = new DeleteAdminFilesAfterBuildCommand($this->filesystem);
    }

    public function testCommandAbortsOnNegativeConfirmation(): void
    {
        $this->filesystem->expects($this->never())->method('remove');

        $commandTester = new CommandTester($this->command);
        $commandTester->setInputs(['no']);
        $commandTester->execute([]);

        $commandTester->assertCommandIsSuccessful();
        static::assertStringContainsString('Command aborted!', $commandTester->getDisplay());
    }

    public function testCommandDeletesFilesOnConfirmation(): void
    {
        $this->filesystem->expects($this->atLeast(1))->method('remove');

        $commandTester = new CommandTester($this->command);
        $commandTester->setInputs(['yes']);
        $commandTester->execute([]);

        $commandTester->assertCommandIsSuccessful();
        static::assertStringContainsString('All unnecessary files of the administration after the build process have been deleted.', $commandTester->getDisplay());
    }

    public function testDeleteEmptyDirectoriesSkipsNonExistentDirectory(): void
    {
        $this->filesystem->expects($this->never())
            ->method('remove');

        $command = new DeleteAdminFilesAfterBuildCommand($this->filesystem);
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('deleteEmptyDirectories');

        $method->invoke($command, '/non/existent/directory');
    }

    public function testDeleteEmptyDirectoriesRemovesSingleEmptyDirectory(): void
    {
        $testDir = sys_get_temp_dir() . '/test_empty_dir_' . uniqid();
        $fs = new Filesystem();
        $fs->mkdir($testDir);

        $this->filesystem->expects($this->once())
            ->method('remove')
            ->with($testDir);

        $command = new DeleteAdminFilesAfterBuildCommand($this->filesystem);
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('deleteEmptyDirectories');

        $method->invoke($command, $testDir);

        $fs->remove($testDir);
    }

    public function testDeleteEmptyDirectoriesRemovesNestedEmptyDirectories(): void
    {
        $testDir = sys_get_temp_dir() . '/test_nested_' . uniqid();
        $level1 = $testDir . '/level1';
        $level2 = $level1 . '/level2';
        $level3 = $level2 . '/level3';

        $fs = new Filesystem();
        $fs->mkdir($level3);

        $this->filesystem->expects($this->exactly(4))
            ->method('remove')
            ->willReturnCallback(function ($dir) use ($level3, $level2, $level1, $testDir, $fs): void {
                static $callCount = 0;
                $expectedDirs = [$level3, $level2, $level1, $testDir];

                $this->assertStringContainsString($expectedDirs[$callCount], $dir);
                $fs->remove($dir);
                ++$callCount;
            });

        $command = new DeleteAdminFilesAfterBuildCommand($this->filesystem);
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('deleteEmptyDirectories');

        $method->invoke($command, $testDir);
        static::assertDirectoryDoesNotExist($testDir);
    }

    public function testDeleteEmptyDirectoriesSkipsUnreadableDirectory(): void
    {
        $testDir = sys_get_temp_dir() . '/test_empty_dir_' . uniqid();
        $fs = new Filesystem();
        $fs->mkdir($testDir, 0000);

        $this->filesystem->expects($this->never())
            ->method('remove')
            ->with($testDir);

        $command = new DeleteAdminFilesAfterBuildCommand($this->filesystem);
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('deleteEmptyDirectories');

        $method->invoke($command, $testDir);

        $fs->chmod($testDir, 0755);
        $fs->remove($testDir);
    }

    public function testRemoveDirectorySkipsUnreadableDirectory(): void
    {
        $testDir = sys_get_temp_dir() . '/test_empty_dir_' . uniqid();
        $fs = new Filesystem();
        $fs->mkdir($testDir, 0000);

        $this->filesystem->expects($this->never())
            ->method('remove')
            ->with($testDir);

        $command = new DeleteAdminFilesAfterBuildCommand($this->filesystem);
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('removeDirectory');

        $method->invoke($command, $testDir);

        $fs->chmod($testDir, 0755);
        $fs->remove($testDir);
    }

    public function testRemoveDirectorySkipsSnippetDirectory(): void
    {
        $testDir = sys_get_temp_dir() . '/test_dir_' . uniqid();
        $snippetDir = $testDir . '/snippet';
        $fs = new Filesystem();
        $fs->mkdir($snippetDir);

        $this->filesystem->expects($this->never())
            ->method('remove');

        $command = new DeleteAdminFilesAfterBuildCommand($this->filesystem);
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('removeDirectory');

        $method->invoke($command, $snippetDir);

        static::assertDirectoryExists($snippetDir);
        $fs->remove($testDir);
    }

    public function testRemoveDirectorySkipsNestedSnippetDirectory(): void
    {
        $testDir = sys_get_temp_dir() . '/test_nested_snippet_' . uniqid();
        $nestedSnippet = $testDir . '/some/path/snippet';
        $fs = new Filesystem();
        $fs->mkdir($nestedSnippet);

        $this->filesystem->expects($this->never())
            ->method('remove');

        $command = new DeleteAdminFilesAfterBuildCommand($this->filesystem);
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('removeDirectory');

        $method->invoke($command, $nestedSnippet);

        static::assertDirectoryExists($nestedSnippet);
        $fs->remove($testDir);
    }
}
