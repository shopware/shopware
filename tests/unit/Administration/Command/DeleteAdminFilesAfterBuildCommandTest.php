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
            static::assertNotContains(
                \dirname($snippetDirectory),
                $deleted,
                'A directory keeping a snippet directory alive must not be deleted itself.'
            );
            static::assertNotSame([], array_values(array_filter(
                $deleted,
                static fn (string $path): bool => str_starts_with($path, \dirname($snippetDirectory) . '/')
            )), 'The files around the snippet directory are cleaned up, so the snippet guard was really hit.');
        }
    }

    #[TestDox('directories that do not exist are not passed to the filesystem')]
    public function testNonExistingDirectoriesAreSkipped(): void
    {
        $missingDirectory = $this->administrationDirectory() . '/Resources/app/administration/src/app/asyncComponent';
        $existingDirectory = $this->administrationDirectory() . '/Resources/app/administration/src/app/adapter';

        static::assertDirectoryDoesNotExist($missingDirectory);

        $deleted = $this->runCommandRecordingDeletions();

        static::assertNotContains($missingDirectory, $deleted);
        static::assertContains($existingDirectory, $deleted);
    }

    #[TestDox('a directory whose cleanup throws is kept and the run continues')]
    public function testThrowingDirectoryCleanupIsKeptAndTheRunContinues(): void
    {
        $adapterDirectory = $this->administrationDirectory() . '/Resources/app/administration/src/app/adapter';
        $packageLock = $this->administrationDirectory() . '/Resources/app/administration/package-lock.json';

        $deleted = [];
        $this->filesystem->expects($this->atLeastOnce())
            ->method('remove')
            ->willReturnCallback(static function (string|iterable $files) use ($adapterDirectory, &$deleted): void {
                foreach (\is_iterable($files) ? $files : [$files] as $file) {
                    if (str_starts_with((string) $file, $adapterDirectory . '/')) {
                        throw new \UnexpectedValueException('cannot be traversed');
                    }
                    $deleted[] = (string) $file;
                }
            });

        $commandTester = new CommandTester($this->command);
        $commandTester->setInputs(['yes']);
        $commandTester->execute([]);

        $commandTester->assertCommandIsSuccessful();
        static::assertNotContains($adapterDirectory, $deleted, 'A directory whose cleanup failed must not be deleted itself.');
        static::assertContains($packageLock, $deleted, 'The cleanup continues after the failed directory.');
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
