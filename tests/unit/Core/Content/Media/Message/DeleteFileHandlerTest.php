<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Message;

use League\Flysystem\DirectoryListing;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Message\DeleteFileHandler;
use Shopware\Core\Content\Media\Message\DeleteFileMessage;

/**
 * @internal
 */
#[CoversClass(DeleteFileHandler::class)]
class DeleteFileHandlerTest extends TestCase
{
    private MockObject&FilesystemOperator $filesystem;

    private DeleteFileHandler $deleteFileHandler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->filesystem = $this->createMock(FilesystemOperator::class);

        $this->deleteFileHandler = new DeleteFileHandler($this->filesystem, $this->filesystem);
    }

    public function testItDeletesFilesOnly(): void
    {
        $paths = [
            'media/12/34/56/foo.bar',
            'media/12/34/56/bar.baz',
            'media/23/45/65/foo.bar',
            'media/23/56/78/bar.baz',
        ];

        $matcher = $this->exactly(4);
        $this->filesystem->expects($matcher)->method('delete')->willReturnCallback(
            static function (string $path) use ($paths, $matcher): void {
                static::assertSame($paths[$matcher->numberOfInvocations() - 1], $path);
            }
        );

        $this->filesystem->expects($this->never())->method('listContents');

        $message = new DeleteFileMessage($paths);
        $this->deleteFileHandler->__invoke($message);
    }

    public function testItDeletesFilesAndEmptyDirectories(): void
    {
        $paths = [
            'media/12/34/56/foo.bar',
            'media/12/34/56/bar.baz',
            'media/23/45/65/foo.bar',
            'media/23/56/78/bar.baz',
        ];

        $this->filesystem->expects($this->exactly(10))
            ->method('listContents')
            ->willReturnOnConsecutiveCalls(
                new DirectoryListing(['bar.baz']),
                new DirectoryListing([]),
                new DirectoryListing([]),
                new DirectoryListing([]),
                new DirectoryListing([]),
                new DirectoryListing([]),
                new DirectoryListing(['56']),
                new DirectoryListing([]),
                new DirectoryListing([]),
                new DirectoryListing([])
            );

        $directories = [
            'media/12/34/56',
            'media/12/34',
            'media/12',
            'media/23/45/65',
            'media/23/45',
            'media/23/56/78',
            'media/23/56',
            'media/23',
        ];

        $matcher = $this->exactly(8);
        $this->filesystem->expects($matcher)->method('deleteDirectory')->willReturnCallback(
            static function (string $path) use ($directories, $matcher): void {
                static::assertSame($directories[$matcher->numberOfInvocations() - 1], $path);
            }
        );

        $message = new DeleteFileMessage($paths, deleteEmptyDirectories: true);
        $this->deleteFileHandler->__invoke($message);
    }
}
