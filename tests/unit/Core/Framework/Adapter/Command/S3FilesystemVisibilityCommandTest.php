<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Command;

use League\Flysystem\DirectoryAttributes;
use League\Flysystem\DirectoryListing;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Command\S3FilesystemVisibilityCommand;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(S3FilesystemVisibilityCommand::class)]
class S3FilesystemVisibilityCommandTest extends TestCase
{
    #[TestDox('Files are set private in the private bucket and public in all other buckets, directories are skipped')]
    public function testSetsVisibilityPerBucket(): void
    {
        $privateFilesystem = $this->createFilesystemWithFile('invoice.pdf', 'private');
        $publicFilesystem = $this->createFilesystemWithFile('logo.png', 'public');

        $emptyFilesystems = [];
        for ($i = 0; $i < 3; ++$i) {
            $filesystem = $this->createMock(FilesystemOperator::class);
            $filesystem->method('listContents')->willReturn(new DirectoryListing([]));
            $filesystem->expects($this->never())->method('setVisibility');
            $emptyFilesystems[] = $filesystem;
        }

        $commandTester = new CommandTester(new S3FilesystemVisibilityCommand(
            $privateFilesystem,
            $publicFilesystem,
            ...$emptyFilesystems
        ));

        static::assertSame(Command::SUCCESS, $commandTester->execute([], ['interactive' => false]));
        static::assertStringContainsString('Finished setting visibility of objects in all pre-defined buckets.', $commandTester->getDisplay());
    }

    #[TestDox('No visibility is changed when the warning is not confirmed')]
    public function testDoesNothingWithoutConfirmation(): void
    {
        $filesystems = [];
        for ($i = 0; $i < 5; ++$i) {
            $filesystem = $this->createMock(FilesystemOperator::class);
            $filesystem->expects($this->never())->method('listContents');
            $filesystem->expects($this->never())->method('setVisibility');
            $filesystems[] = $filesystem;
        }

        $commandTester = new CommandTester(new S3FilesystemVisibilityCommand(...$filesystems));
        $commandTester->setInputs(['no']);

        static::assertSame(Command::SUCCESS, $commandTester->execute([]));
        static::assertStringContainsString('this command will set all of them public', $commandTester->getDisplay());
    }

    private function createFilesystemWithFile(string $path, string $expectedVisibility): MockObject&FilesystemOperator
    {
        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->method('listContents')->willReturn(new DirectoryListing([
            new FileAttributes($path),
            new DirectoryAttributes('media'),
        ]));
        $filesystem
            ->expects($this->once())
            ->method('setVisibility')
            ->with($path, $expectedVisibility);

        return $filesystem;
    }
}
