<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Message;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\Message\GenerateThumbnailsHandler;
use Shopware\Core\Content\Media\Message\UpdateThumbnailsMessage;
use Shopware\Core\Content\Media\Thumbnail\ThumbnailService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(GenerateThumbnailsHandler::class)]
class GenerateThumbnailsHandlerTest extends TestCase
{
    public function testUpdateThumbnailsContinuesBatchWhenSingleMediaFails(): void
    {
        $failingId = Uuid::randomHex();
        $succeedingId = Uuid::randomHex();

        $failing = new MediaEntity();
        $failing->setId($failingId);
        $succeeding = new MediaEntity();
        $succeeding->setId($succeedingId);

        $mediaRepository = new StaticEntityRepository([new MediaCollection([$failing, $succeeding])]);

        $handledIds = [];
        $thumbnailService = $this->createMock(ThumbnailService::class);
        $thumbnailService->expects($this->exactly(2))
            ->method('updateThumbnails')
            ->willReturnCallback(function (MediaEntity $media) use (&$handledIds, $failingId): int {
                $handledIds[] = $media->getId();

                if ($media->getId() === $failingId) {
                    throw new \RuntimeException('thumbnail generation failed');
                }

                return 0;
            });

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');

        $handler = new GenerateThumbnailsHandler($thumbnailService, $mediaRepository, $logger);

        $message = new UpdateThumbnailsMessage();
        $message->setMediaIds([$failingId, $succeedingId]);
        $message->setStrict(false);
        $message->setContext(Context::createDefaultContext());

        $handler($message);

        static::assertSame([$failingId, $succeedingId], $handledIds);
    }

    public function testUpdateThumbnailsForwardsStrictAndForceFlags(): void
    {
        $mediaId = Uuid::randomHex();
        $media = new MediaEntity();
        $media->setId($mediaId);

        $mediaRepository = new StaticEntityRepository([new MediaCollection([$media])]);

        $thumbnailService = $this->createMock(ThumbnailService::class);
        $thumbnailService->expects($this->once())
            ->method('updateThumbnails')
            ->with($media, static::anything(), true, true)
            ->willReturn(1);

        $handler = new GenerateThumbnailsHandler($thumbnailService, $mediaRepository, static::createStub(LoggerInterface::class));

        $message = new UpdateThumbnailsMessage();
        $message->setMediaIds([$mediaId]);
        $message->setStrict(true);
        $message->setForce(true);
        $message->setContext(Context::createDefaultContext());

        $handler($message);
    }
}
