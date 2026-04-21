<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Upload;

use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use League\Flysystem\UnableToDeleteFile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\Message\GenerateThumbnailsMessage;
use Shopware\Core\Content\Media\Thumbnail\ThumbnailService;
use Shopware\Core\Content\Media\Upload\MediaFileCleanupService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\MessageBus\CollectingMessageBus;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(MediaFileCleanupService::class)]
class MediaFileCleanupServiceTest extends TestCase
{
    private FilesystemOperator $filesystemPublic;

    private FilesystemOperator $filesystemPrivate;

    private ThumbnailService&MockObject $thumbnailService;

    private CollectingMessageBus $messageBus;

    protected function setUp(): void
    {
        $this->filesystemPublic = new Filesystem(new InMemoryFilesystemAdapter());
        $this->filesystemPrivate = new Filesystem(new InMemoryFilesystemAdapter());
        $this->thumbnailService = $this->createMock(ThumbnailService::class);
        $this->messageBus = new CollectingMessageBus();
    }

    public function testRemoveOldMediaDataDeletesPublicFile(): void
    {
        $service = new MediaFileCleanupService(
            $this->filesystemPublic,
            $this->filesystemPrivate,
            $this->thumbnailService,
            $this->messageBus,
            false,
        );

        $this->filesystemPublic->write('media/ab/cd/test.jpg', 'payload');
        $this->filesystemPrivate->write('media/ab/cd/test.jpg', 'other');

        $context = Context::createDefaultContext();
        $media = new MediaEntity();
        $media->setId('media-1');
        $media->assign(['path' => 'media/ab/cd/test.jpg', 'fileName' => 'test', 'fileExtension' => 'jpg']);
        $media->setPrivate(false);
        $media->setThumbnails(new MediaThumbnailCollection());

        $this->thumbnailService->expects($this->once())
            ->method('deleteThumbnails')
            ->with($media, $context);

        $service->removeOldMediaData($media, $context);

        static::assertFalse($this->filesystemPublic->fileExists('media/ab/cd/test.jpg'));
        static::assertTrue($this->filesystemPrivate->fileExists('media/ab/cd/test.jpg'));
    }

    public function testRemoveOldMediaDataDeletesPrivateFile(): void
    {
        $service = new MediaFileCleanupService(
            $this->filesystemPublic,
            $this->filesystemPrivate,
            $this->thumbnailService,
            $this->messageBus,
            false,
        );

        $this->filesystemPublic->write('media/ab/cd/private.pdf', 'decoy');
        $this->filesystemPrivate->write('media/ab/cd/private.pdf', 'payload');

        $media = new MediaEntity();
        $media->setId('media-2');
        $media->assign(['path' => 'media/ab/cd/private.pdf', 'fileName' => 'private', 'fileExtension' => 'pdf']);
        $media->setPrivate(true);
        $media->setThumbnails(new MediaThumbnailCollection());

        $service->removeOldMediaData($media, Context::createDefaultContext());

        static::assertFalse($this->filesystemPrivate->fileExists('media/ab/cd/private.pdf'));
        static::assertTrue($this->filesystemPublic->fileExists('media/ab/cd/private.pdf'));
    }

    public function testRemoveOldMediaDataSkipsWithNoFile(): void
    {
        $service = new MediaFileCleanupService(
            $this->filesystemPublic,
            $this->filesystemPrivate,
            $this->thumbnailService,
            $this->messageBus,
            false,
        );

        $this->filesystemPublic->write('media/ab/cd/keep-public.jpg', 'keep');
        $this->filesystemPrivate->write('media/ab/cd/keep-private.jpg', 'keep');

        $media = new MediaEntity();
        $media->setId('media-3');
        $media->setThumbnails(new MediaThumbnailCollection());

        $this->thumbnailService->expects($this->never())
            ->method('deleteThumbnails');

        $service->removeOldMediaData($media, Context::createDefaultContext());

        static::assertTrue($this->filesystemPublic->fileExists('media/ab/cd/keep-public.jpg'));
        static::assertTrue($this->filesystemPrivate->fileExists('media/ab/cd/keep-private.jpg'));
    }

    public function testRemoveOldMediaDataSwallowsDeleteException(): void
    {
        $throwingFilesystem = new Filesystem(new class extends InMemoryFilesystemAdapter {
            public function delete(string $path): void
            {
                throw UnableToDeleteFile::atLocation($path);
            }
        });

        $service = new MediaFileCleanupService(
            $throwingFilesystem,
            $this->filesystemPrivate,
            $this->thumbnailService,
            $this->messageBus,
            false,
        );

        $context = Context::createDefaultContext();
        $media = new MediaEntity();
        $media->setId('media-4');
        $media->assign(['path' => 'media/ab/cd/gone.jpg', 'fileName' => 'gone', 'fileExtension' => 'jpg']);
        $media->setPrivate(false);
        $media->setThumbnails(new MediaThumbnailCollection());

        $this->thumbnailService->expects($this->once())
            ->method('deleteThumbnails')
            ->with($media, $context);

        $service->removeOldMediaData($media, $context);
    }

    public function testRemoveOldMediaDataSkipsThumbnailsWhenRemote(): void
    {
        $service = new MediaFileCleanupService(
            $this->filesystemPublic,
            $this->filesystemPrivate,
            $this->thumbnailService,
            $this->messageBus,
            true,
        );

        $this->filesystemPublic->write('media/ab/cd/file.jpg', 'payload');

        $media = new MediaEntity();
        $media->setId('media-5');
        $media->assign(['path' => 'media/ab/cd/file.jpg', 'fileName' => 'file', 'fileExtension' => 'jpg']);
        $media->setPrivate(false);
        $media->setThumbnails(new MediaThumbnailCollection());

        $this->thumbnailService->expects($this->never())
            ->method('deleteThumbnails');

        $service->removeOldMediaData($media, Context::createDefaultContext());

        static::assertFalse($this->filesystemPublic->fileExists('media/ab/cd/file.jpg'));
    }

    public function testDeleteThumbnailsDelegatesToService(): void
    {
        $service = new MediaFileCleanupService(
            $this->filesystemPublic,
            $this->filesystemPrivate,
            $this->thumbnailService,
            $this->messageBus,
            false,
        );

        $context = Context::createDefaultContext();
        $media = new MediaEntity();
        $media->setId('media-6');

        $this->thumbnailService->expects($this->once())
            ->method('deleteThumbnails')
            ->with($media, $context);

        $service->deleteThumbnails($media, $context);
    }

    public function testDeleteThumbnailsSkipsWhenRemote(): void
    {
        $service = new MediaFileCleanupService(
            $this->filesystemPublic,
            $this->filesystemPrivate,
            $this->thumbnailService,
            $this->messageBus,
            true,
        );

        $media = new MediaEntity();
        $media->setId('media-7');

        $this->thumbnailService->expects($this->never())
            ->method('deleteThumbnails');

        $service->deleteThumbnails($media, Context::createDefaultContext());
    }

    public function testDispatchThumbnailGeneration(): void
    {
        $service = new MediaFileCleanupService(
            $this->filesystemPublic,
            $this->filesystemPrivate,
            $this->thumbnailService,
            $this->messageBus,
            false,
        );

        $service->dispatchThumbnailGeneration('media-8', Context::createDefaultContext());

        $messages = $this->messageBus->getMessages();
        static::assertCount(1, $messages);

        $message = $messages[0]->getMessage();
        static::assertInstanceOf(GenerateThumbnailsMessage::class, $message);
        static::assertSame(['media-8'], $message->getMediaIds());
    }

    public function testDispatchThumbnailGenerationSkipsWhenRemote(): void
    {
        $service = new MediaFileCleanupService(
            $this->filesystemPublic,
            $this->filesystemPrivate,
            $this->thumbnailService,
            $this->messageBus,
            true,
        );

        $service->dispatchThumbnailGeneration('media-9', Context::createDefaultContext());

        static::assertSame([], $this->messageBus->getMessages());
    }
}
