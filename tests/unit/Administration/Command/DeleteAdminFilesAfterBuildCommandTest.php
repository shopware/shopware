<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Administration;
use Shopware\Administration\Command\DeleteAdminFilesAfterBuildCommand;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * @internal
 */
#[Package('framework')]
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

    #[TestDox('snippet directories and their contents survive the cleanup')]
    public function testSnippetDirectoriesAreNeverDeleted(): void
    {
        $snippetDirectories = $this->findSnippetDirectories();
        static::assertNotEmpty(
            $snippetDirectories,
            'The administration components must ship at least one snippet directory, otherwise this test proves nothing.'
        );

        $deleted = $this->runCommandRecordingDeletions();

        foreach ($snippetDirectories as $snippetDirectory) {
            static::assertNotContains($snippetDirectory, $deleted);
            static::assertSame([], array_values(array_filter(
                $deleted,
                static fn (string $path): bool => str_starts_with($path, $snippetDirectory . '/')
            )));
            static::assertContains(
                \dirname($snippetDirectory),
                $deleted,
                'The directory containing the snippet directory is cleaned up, so the snippet guard was really hit.'
            );
        }
    }

    #[TestDox('directories that do not exist are not passed to the filesystem')]
    public function testNonExistingDirectoriesAreSkipped(): void
    {
        $missingDirectory = $this->administrationDirectory() . '/Resources/app/administration/src/app/asyncComponent';
        $existingDirectory = $this->administrationDirectory() . '/Resources/app/administration/src/app/component';

        static::assertDirectoryDoesNotExist($missingDirectory);

        $deleted = $this->runCommandRecordingDeletions();

        static::assertNotContains($missingDirectory, $deleted);
        static::assertContains($existingDirectory, $deleted);
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

    /**
     * Runs the command through its public interface. The filesystem double records the requested
     * deletions instead of performing them, so the real administration sources stay untouched.
     *
     * @return list<string>
     */
    private function runCommandRecordingDeletions(): array
    {
        $deleted = [];

        $this->filesystem->expects($this->atLeastOnce())
            ->method('remove')
            ->willReturnCallback(function (string|iterable $files) use (&$deleted): void {
                foreach (\is_iterable($files) ? $files : [$files] as $file) {
                    $deleted[] = (string) $file;
                }
            });

        $commandTester = new CommandTester($this->command);
        $commandTester->setInputs(['yes']);
        $commandTester->execute([]);

        $commandTester->assertCommandIsSuccessful();

        return $deleted;
    }

    /**
     * @return list<string>
     */
    private function findSnippetDirectories(): array
    {
        $finder = new Finder();
        $finder->in($this->administrationDirectory() . '/Resources/app/administration/src/app/component')
            ->directories()
            ->name('snippet');

        $snippetDirectories = [];
        foreach ($finder as $snippetDirectory) {
            $snippetDirectories[] = (string) $snippetDirectory->getRealPath();
        }

        return $snippetDirectories;
    }

    private function administrationDirectory(): string
    {
        return (new Administration())->getPath();
    }
}
