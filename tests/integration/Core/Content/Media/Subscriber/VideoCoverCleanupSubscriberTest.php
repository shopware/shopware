<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Media\Subscriber;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaType\VideoType;
use Shopware\Core\Content\Media\Service\VideoCoverService;
use Shopware\Core\Content\Test\Media\MediaFixtures;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class VideoCoverCleanupSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;
    use MediaFixtures;

    /**
     * @var EntityRepository<MediaCollection>
     */
    private EntityRepository $mediaRepository;

    private Context $context;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $this->setFixtureContext($this->context);
        $this->mediaRepository = static::getContainer()->get('media.repository');
    }

    public function testCoverReferenceIsRemovedWhenImageGetsDeleted(): void
    {
        $cover = $this->getPng();
        $video = $this->createVideoMediaWithCover($cover->getId());

        $this->mediaRepository->delete([['id' => $cover->getId()]], $this->context);

        $reloaded = $this->getMediaEntity($video->getId());

        // Check that coverMediaId is no longer present in metadata
        $metaData = $reloaded->getMetaData();
        static::assertFalse(
            isset($metaData['video']['coverMediaId']),
            'coverMediaId should be removed from metadata when the cover image is deleted'
        );
    }

    public function testCoverReferenceIsRemovedForAllVideosSharingCover(): void
    {
        $cover = $this->getPng();
        $firstVideo = $this->createVideoMediaWithCover($cover->getId());
        $secondVideo = $this->createVideoMediaWithCover($cover->getId());

        $this->mediaRepository->delete([['id' => $cover->getId()]], $this->context);

        $firstReloaded = $this->getMediaEntity($firstVideo->getId());
        $secondReloaded = $this->getMediaEntity($secondVideo->getId());

        static::assertFalse(isset($firstReloaded->getMetaData()['video']['coverMediaId']));
        static::assertFalse(isset($secondReloaded->getMetaData()['video']['coverMediaId']));
    }

    public function testCoverReferenceStaysWhenOtherMediaIsDeleted(): void
    {
        $cover = $this->getPng();
        $video = $this->createVideoMediaWithCover($cover->getId());
        $unrelated = $this->getTxt();

        $this->mediaRepository->delete([['id' => $unrelated->getId()]], $this->context);

        $reloaded = $this->getMediaEntity($video->getId());

        static::assertSame($cover->getId(), $reloaded->getMetaData()['video']['coverMediaId'] ?? null);
    }

    public function testAssignVideoCoverPersistsMetaData(): void
    {
        $cover = $this->getPng();
        $video = $this->createVideoMedia();

        $service = static::getContainer()->get(VideoCoverService::class);
        static::assertInstanceOf(VideoCoverService::class, $service);
        $service->assignCoverToVideo($video->getId(), $cover->getId(), $this->context);

        $reloaded = $this->getMediaEntity($video->getId());

        static::assertSame($cover->getId(), $reloaded->getMetaData()['video']['coverMediaId'] ?? null);
    }

    public function testRemovingVideoCoverClearsMetaData(): void
    {
        $cover = $this->getPng();
        $video = $this->createVideoMediaWithCover($cover->getId());

        $service = static::getContainer()->get(VideoCoverService::class);
        static::assertInstanceOf(VideoCoverService::class, $service);
        $service->assignCoverToVideo($video->getId(), null, $this->context);

        $reloaded = $this->getMediaEntity($video->getId());

        static::assertNull($reloaded->getMetaData());
    }

    public function testVideoCoverMediaIsLoadedAsExtension(): void
    {
        $cover = $this->getPng();
        $video = $this->createVideoMediaWithCover($cover->getId());

        $reloaded = $this->getMediaEntity($video->getId());

        $extension = $reloaded->getExtension('videoCoverMedia');

        static::assertInstanceOf(MediaEntity::class, $extension);
        static::assertSame($cover->getId(), $extension->getId());
    }

    private function createVideoMediaWithCover(string $coverId): MediaEntity
    {
        return $this->createVideoMedia($coverId);
    }

    private function createVideoMedia(?string $coverId = null): MediaEntity
    {
        $videoId = Uuid::randomHex();
        $metaData = $coverId ? ['video' => ['coverMediaId' => $coverId]] : null;

        $payload = [
            'id' => $videoId,
            'mimeType' => 'video/mp4',
            'fileExtension' => 'mp4',
            'fileName' => 'sample-video-' . $videoId,
            'fileSize' => 1024,
            'mediaType' => new VideoType(),
            'metaData' => $metaData,
        ];

        $this->mediaRepository->create([$payload], $this->context);

        return $this->getMediaEntity($videoId);
    }

    private function getMediaEntity(string $id): MediaEntity
    {
        $entity = $this->mediaRepository
            ->search(new Criteria([$id]), $this->context)
            ->first();

        static::assertNotNull($entity, \sprintf('Media entity "%s" not found', $id));
        static::assertInstanceOf(MediaEntity::class, $entity);

        return $entity;
    }
}
