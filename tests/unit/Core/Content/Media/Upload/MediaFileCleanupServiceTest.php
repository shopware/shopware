<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Upload;

use League\Flysystem\FilesystemOperator;
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
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(MediaFileCleanupService::class)]
class MediaFileCleanupServiceTest extends TestCase
{
    private FilesystemOperator&MockObject $filesystemPublic;

    private FilesystemOperator&MockObject $filesystemPrivate;

    private ThumbnailService&MockObject $thumbnailService;

    private MessageBusInterface&MockObject $messageBus;

    protected function setUp(): void
    {
        $this->filesystemPublic = $this->createMock(FilesystemOperator::class);
        $this->filesystemPrivate = $this->createMock(FilesystemOperator::class);
        $this->thumbnailService = $this->createMock(ThumbnailService::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
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

        $media = new MediaEntity();
        $media->setId('media-1');
        $media->assign(['path' => 'media/ab/cd/test.jpg', 'fileName' => 'test', 'fileExtension' => 'jpg']);
        $media->setPrivate(false);
        $media->setThumbnails(new MediaThumbnailCollection());

        $this->filesystemPublic->expects($this->once())
            ->method('delete')
            ->with('media/ab/cd/test.jpg');

        $this->thumbnailService->expects($this->once())
            ->method('deleteThumbnails');

        $service->removeOldMediaData($media, Context::createDefaultContext());
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

        $media = new MediaEntity();
        $media->setId('media-2');
        $media->assign(['path' => 'media/ab/cd/private.pdf', 'fileName' => 'private', 'fileExtension' => 'pdf']);
        $media->setPrivate(true);
        $media->setThumbnails(new MediaThumbnailCollection());

        $this->filesystemPrivate->expects($this->once())
            ->method('delete')
            ->with('media/ab/cd/private.pdf');

        $this->filesystemPublic->expects($this->never())
            ->method('delete');

        $service->removeOldMediaData($media, Context::createDefaultContext());
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

        $media = new MediaEntity();
        $media->setId('media-3');
        $media->setThumbnails(new MediaThumbnailCollection());

        $this->filesystemPublic->expects($this->never())->method('delete');
        $this->filesystemPrivate->expects($this->never())->method('delete');

        $service->removeOldMediaData($media, Context::createDefaultContext());
    }

    public function testRemoveOldMediaDataSwallowsDeleteException(): void
    {
        $service = new MediaFileCleanupService(
            $this->filesystemPublic,
            $this->filesystemPrivate,
            $this->thumbnailService,
            $this->messageBus,
            false,
        );

        $media = new MediaEntity();
        $media->setId('media-4');
        $media->assign(['path' => 'media/ab/cd/gone.jpg', 'fileName' => 'gone', 'fileExtension' => 'jpg']);
        $media->setPrivate(false);
        $media->setThumbnails(new MediaThumbnailCollection());

        $this->filesystemPublic->method('delete')
            ->willThrowException(UnableToDeleteFile::atLocation('media/ab/cd/gone.jpg'));

        $this->thumbnailService->expects($this->once())
            ->method('deleteThumbnails')
            ->with($media);

        $service->removeOldMediaData($media, Context::createDefaultContext());
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

        $media = new MediaEntity();
        $media->setId('media-5');
        $media->assign(['path' => 'media/ab/cd/file.jpg', 'fileName' => 'file', 'fileExtension' => 'jpg']);
        $media->setPrivate(false);
        $media->setThumbnails(new MediaThumbnailCollection());

        $this->thumbnailService->expects($this->never())
            ->method('deleteThumbnails');

        $service->removeOldMediaData($media, Context::createDefaultContext());
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

        $media = new MediaEntity();
        $media->setId('media-6');

        $this->thumbnailService->expects($this->once())
            ->method('deleteThumbnails')
            ->with($media);

        $service->deleteThumbnails($media, Context::createDefaultContext());
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

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with(static::callback(function (GenerateThumbnailsMessage $message): bool {
                static::assertSame(['media-8'], $message->getMediaIds());

                return true;
            }))
            ->willReturn(new Envelope(new GenerateThumbnailsMessage()));

        $service->dispatchThumbnailGeneration('media-8', Context::createDefaultContext());
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

        $this->messageBus->expects($this->never())
            ->method('dispatch');

        $service->dispatchThumbnailGeneration('media-9', Context::createDefaultContext());
    }
}
