<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Commands;

use Doctrine\DBAL\Connection;
use League\Flysystem\DirectoryListing;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Commands\DeleteThumbnailsCommand;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(DeleteThumbnailsCommand::class)]
class DeleteThumbnailsCommandTest extends TestCase
{
    public function testExecuteWithRemoteThumbnailsDisabled(): void
    {
        $command = new DeleteThumbnailsCommand(
            static::createStub(Connection::class),
            static::createStub(EntityRepository::class),
            static::createStub(FilesystemOperator::class),
            static::createStub(FilesystemOperator::class),
            false
        );

        $commandTester = new CommandTester($command);
        $commandTester->setInputs(['yes']);
        $commandTester->execute([]);

        static::assertStringContainsStringIgnoringLineEndings('// Deleting thumbnails is only supported when remote thumbnail is enabled.', trim($commandTester->getDisplay()));
    }

    public function testExecuteWithRemoteThumbnailsDisabledAndForce(): void
    {
        $connection = $this->createMock(Connection::class);
        $thumbnailRepository = $this->createMock(EntityRepository::class);
        $filesystemPublic = $this->createMock(FilesystemOperator::class);
        $filesystemPrivate = $this->createMock(FilesystemOperator::class);
        $command = new DeleteThumbnailsCommand($connection, $thumbnailRepository, $filesystemPublic, $filesystemPrivate, false);

        $commandTester = new CommandTester($command);
        $commandTester->setInputs(['yes']);

        $thumbnailId = Uuid::randomHex();
        $thumbnailPath = 'thumbnail/aa/bb/cc/1786525629/test_100x100.png';
        $connection->expects($this->once())
            ->method('fetchAllKeyValue')
            ->with('SELECT LOWER(HEX(`id`)) as id, `path` FROM `media_thumbnail`')
            ->willReturn([$thumbnailId => $thumbnailPath]);

        $filesystemPublic->expects($this->once())
            ->method('listContents')
            ->with('thumbnail', true)
            ->willReturn(new DirectoryListing([
                new FileAttributes($thumbnailPath),
                new FileAttributes('thumbnail/dd/ee/ff/1776341071/orphan_100x100.png'),
                new FileAttributes('thumbnail/dd/ee/ff/1779799284/orphan_100x100.png'),
            ]));

        $filesystemPrivate->expects($this->once())
            ->method('listContents')
            ->with('thumbnail', true)
            ->willReturn(new DirectoryListing([]));

        $thumbnailRepository->expects($this->once())
            ->method('delete')
            ->with([['id' => $thumbnailId]], static::isInstanceOf(Context::class));

        $connection->expects($this->once())
            ->method('executeStatement')
            ->with('UPDATE `media` SET `thumbnails_ro` = NULL;');

        $filesystemPublic->expects($this->once())
            ->method('deleteDirectory')
            ->with('thumbnail');

        $filesystemPrivate->expects($this->once())
            ->method('deleteDirectory')
            ->with('thumbnail');

        $commandTester->execute(['--force' => true]);

        $commandTester->assertCommandIsSuccessful();

        $display = $commandTester->getDisplay();
        static::assertMatchesRegularExpression('/Deleted\s+3\b/', $display);
        static::assertStringContainsString('Successfully deleted all thumbnails records and thumbnails files.', $display);
    }

    public function testExecuteFailsWhenForceAndOrphansAreCombined(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchAllKeyValue');

        $command = new DeleteThumbnailsCommand(
            $connection,
            static::createStub(EntityRepository::class),
            static::createStub(FilesystemOperator::class),
            static::createStub(FilesystemOperator::class),
            false
        );

        $commandTester = new CommandTester($command);

        static::assertSame(Command::INVALID, $commandTester->execute([
            '--force' => true,
            '--orphans' => true,
        ]));

        static::assertStringContainsStringIgnoringLineEndings('The options --force and --orphans cannot be combined', $commandTester->getDisplay());
    }

    public function testExecuteWithOrphansOptionOnlyDeletesOrphanedFiles(): void
    {
        $connection = $this->createMock(Connection::class);
        $thumbnailRepository = $this->createMock(EntityRepository::class);
        $filesystemPublic = $this->createMock(FilesystemOperator::class);
        $filesystemPrivate = $this->createMock(FilesystemOperator::class);
        $command = new DeleteThumbnailsCommand($connection, $thumbnailRepository, $filesystemPublic, $filesystemPrivate, false);

        $commandTester = new CommandTester($command);

        $thumbnailPath = 'thumbnail/aa/bb/cc/1786525629/test_100x100.png';
        $orphanedPaths = [
            'thumbnail/dd/ee/ff/1776341071/orphan_100x100.png',
            'thumbnail/dd/ee/ff/1779799284/orphan_100x100.png',
        ];
        $connection->expects($this->once())
            ->method('fetchAllKeyValue')
            ->with('SELECT LOWER(HEX(`id`)) as id, `path` FROM `media_thumbnail`')
            ->willReturn([Uuid::randomHex() => $thumbnailPath]);

        $filesystemPublic->expects($this->once())
            ->method('listContents')
            ->with('thumbnail', true)
            ->willReturn(new DirectoryListing([
                new FileAttributes($thumbnailPath),
                new FileAttributes($orphanedPaths[0]),
                new FileAttributes($orphanedPaths[1]),
            ]));

        $filesystemPrivate->expects($this->once())
            ->method('listContents')
            ->with('thumbnail', true)
            ->willReturn(new DirectoryListing([]));

        $deletedPaths = [];
        $filesystemPublic->expects($this->exactly(2))
            ->method('delete')
            ->willReturnCallback(function (string $path) use (&$deletedPaths): void {
                $deletedPaths[] = $path;
            });

        $filesystemPublic->expects($this->never())->method('deleteDirectory');
        $filesystemPrivate->expects($this->never())->method('deleteDirectory');
        $filesystemPrivate->expects($this->never())->method('delete');
        $thumbnailRepository->expects($this->never())->method('delete');
        $connection->expects($this->never())->method('executeStatement');

        $commandTester->execute(['--orphans' => true]);

        $commandTester->assertCommandIsSuccessful();

        static::assertSame($orphanedPaths, $deletedPaths);

        $display = $commandTester->getDisplay();
        static::assertMatchesRegularExpression('/Deleted \(orphaned\)\s+2\b/', $display);
        static::assertMatchesRegularExpression('/Kept \(referenced\)\s+1\b/', $display);
        static::assertStringContainsString('Successfully deleted all orphaned thumbnail files.', $display);
    }

    public function testExecuteWithRemoteThumbnailsEnabled(): void
    {
        $connection = $this->createMock(Connection::class);
        $thumbnailRepository = $this->createMock(EntityRepository::class);
        $filesystemPublic = static::createStub(FilesystemOperator::class);
        $filesystemPrivate = static::createStub(FilesystemOperator::class);
        $filesystemPublic->method('listContents')->willReturn(new DirectoryListing([]));
        $filesystemPrivate->method('listContents')->willReturn(new DirectoryListing([]));
        $command = new DeleteThumbnailsCommand(
            $connection,
            $thumbnailRepository,
            $filesystemPublic,
            $filesystemPrivate,
            true
        );

        $commandTester = new CommandTester($command);
        $commandTester->setInputs(['yes']);

        $thumbnails = [
            Uuid::randomHex() => 'thumbnail/aa/bb/cc/1786525629/test_100x100.png',
            Uuid::randomHex() => 'thumbnail/aa/bb/cc/1786525629/test_200x200.png',
        ];
        $connection->expects($this->once())
            ->method('fetchAllKeyValue')
            ->with('SELECT LOWER(HEX(`id`)) as id, `path` FROM `media_thumbnail`')
            ->willReturn($thumbnails);

        $thumbnailRepository->expects($this->once())
            ->method('delete')
            ->with(array_map(static fn (string $id) => ['id' => $id], array_keys($thumbnails)), static::isInstanceOf(Context::class));

        $connection->expects($this->once())
            ->method('executeStatement')
            ->with('UPDATE `media` SET `thumbnails_ro` = NULL;');
        $commandTester->execute([]);

        $commandTester->assertCommandIsSuccessful();

        static::assertStringContainsString('Successfully deleted all thumbnails records and thumbnails files.', $commandTester->getDisplay());
    }
}
