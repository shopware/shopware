<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Content\Media\MediaType\ImageType;
use Shopware\Core\Content\Media\MediaType\MediaType;
use Shopware\Core\Content\Media\MediaType\VideoType;
use Shopware\Core\Content\Media\Service\VideoCoverService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(VideoCoverService::class)]
class VideoCoverServiceTest extends TestCase
{
    /**
     * @var EntityRepository<MediaCollection>&MockObject
     */
    private EntityRepository $mediaRepository;

    private VideoCoverService $service;

    private Context $context;

    protected function setUp(): void
    {
        $this->mediaRepository = $this->createMock(EntityRepository::class);
        $this->service = new VideoCoverService($this->mediaRepository);
        $this->context = Context::createDefaultContext();
    }

    public function testAssignCoverToVideoSuccessfully(): void
    {
        $videoId = Uuid::randomHex();
        $coverId = Uuid::randomHex();

        $video = $this->createMediaEntity($videoId, 'video/mp4', new VideoType(), null);
        $cover = $this->createMediaEntity($coverId, 'image/png', new ImageType(), null);

        $this->mediaRepository
            ->expects($this->exactly(2))
            ->method('search')
            ->willReturnCallback(function (Criteria $criteria, Context $context) use ($videoId, $coverId, $video, $cover) {
                $id = $criteria->getIds()[0];
                if ($id === $videoId) {
                    return $this->createSearchResult($video);
                }
                if ($id === $coverId) {
                    return $this->createSearchResult($cover);
                }

                return $this->createSearchResult(null);
            });

        $this->mediaRepository
            ->expects($this->once())
            ->method('update')
            ->with(
                static::callback(static function (array $updates) use ($videoId, $coverId) {
                    static::assertCount(1, $updates);
                    static::assertSame($videoId, $updates[0]['id']);
                    static::assertSame(['video' => ['coverMediaId' => $coverId]], $updates[0]['metaData']);

                    return true;
                }),
                static::anything()
            );

        $this->service->assignCoverToVideo($videoId, $coverId, $this->context);
    }

    public function testRemoveCoverFromVideo(): void
    {
        $videoId = Uuid::randomHex();
        $existingCoverId = Uuid::randomHex();

        $video = $this->createMediaEntity(
            $videoId,
            'video/mp4',
            new VideoType(),
            ['video' => ['coverMediaId' => $existingCoverId]]
        );

        $this->mediaRepository
            ->expects($this->once())
            ->method('search')
            ->willReturn($this->createSearchResult($video));

        $this->mediaRepository
            ->expects($this->once())
            ->method('update')
            ->with(
                static::callback(static function (array $updates) use ($videoId) {
                    static::assertCount(1, $updates);
                    static::assertSame($videoId, $updates[0]['id']);
                    static::assertNull($updates[0]['metaData']);

                    return true;
                }),
                static::anything()
            );

        $this->service->assignCoverToVideo($videoId, null, $this->context);
    }

    public function testThrowsExceptionWhenVideoNotFound(): void
    {
        $videoId = Uuid::randomHex();

        $this->mediaRepository
            ->expects($this->once())
            ->method('search')
            ->willReturn($this->createSearchResult(null));

        $this->expectExceptionObject(MediaException::mediaNotFound($videoId));

        $this->service->assignCoverToVideo($videoId, Uuid::randomHex(), $this->context);
    }

    public function testThrowsExceptionWhenMediaIsNotVideo(): void
    {
        $mediaId = Uuid::randomHex();
        $coverId = Uuid::randomHex();

        $media = $this->createMediaEntity($mediaId, 'image/png', new ImageType(), null);

        $this->mediaRepository
            ->expects($this->once())
            ->method('search')
            ->willReturn($this->createSearchResult($media));

        $this->expectExceptionObject(MediaException::mediaFileTypeNotSupported($mediaId, 'video'));

        $this->service->assignCoverToVideo($mediaId, $coverId, $this->context);
    }

    public function testThrowsExceptionWhenCoverNotFound(): void
    {
        $videoId = Uuid::randomHex();
        $coverId = Uuid::randomHex();

        $video = $this->createMediaEntity($videoId, 'video/mp4', new VideoType(), null);

        $this->mediaRepository
            ->expects($this->exactly(2))
            ->method('search')
            ->willReturnCallback(function (Criteria $criteria, Context $context) use ($videoId, $video) {
                $id = $criteria->getIds()[0];
                if ($id === $videoId) {
                    return $this->createSearchResult($video);
                }

                return $this->createSearchResult(null);
            });

        $this->expectExceptionObject(MediaException::mediaNotFound($coverId));

        $this->service->assignCoverToVideo($videoId, $coverId, $this->context);
    }

    public function testThrowsExceptionWhenCoverIsNotImage(): void
    {
        $videoId = Uuid::randomHex();
        $coverId = Uuid::randomHex();

        $video = $this->createMediaEntity($videoId, 'video/mp4', new VideoType(), null);
        $cover = $this->createMediaEntity($coverId, 'video/mp4', new VideoType(), null);

        $this->mediaRepository
            ->expects($this->exactly(2))
            ->method('search')
            ->willReturnCallback(function (Criteria $criteria, Context $context) use ($videoId, $coverId, $video, $cover) {
                $id = $criteria->getIds()[0];
                if ($id === $videoId) {
                    return $this->createSearchResult($video);
                }
                if ($id === $coverId) {
                    return $this->createSearchResult($cover);
                }

                return $this->createSearchResult(null);
            });

        $this->expectExceptionObject(MediaException::mediaFileTypeNotSupported($coverId, 'image'));

        $this->service->assignCoverToVideo($videoId, $coverId, $this->context);
    }

    public function testPreservesExistingMetaData(): void
    {
        $videoId = Uuid::randomHex();
        $coverId = Uuid::randomHex();

        $video = $this->createMediaEntity(
            $videoId,
            'video/mp4',
            new VideoType(),
            ['width' => 1920, 'height' => 1080]
        );
        $cover = $this->createMediaEntity($coverId, 'image/png', new ImageType(), null);

        $this->mediaRepository
            ->expects($this->exactly(2))
            ->method('search')
            ->willReturnCallback(function (Criteria $criteria, Context $context) use ($videoId, $coverId, $video, $cover) {
                $id = $criteria->getIds()[0];
                if ($id === $videoId) {
                    return $this->createSearchResult($video);
                }
                if ($id === $coverId) {
                    return $this->createSearchResult($cover);
                }

                return $this->createSearchResult(null);
            });

        $this->mediaRepository
            ->expects($this->once())
            ->method('update')
            ->with(
                static::callback(static function (array $updates) use ($videoId, $coverId) {
                    static::assertCount(1, $updates);
                    static::assertSame($videoId, $updates[0]['id']);
                    static::assertSame(
                        [
                            'width' => 1920,
                            'height' => 1080,
                            'video' => ['coverMediaId' => $coverId],
                        ],
                        $updates[0]['metaData']
                    );

                    return true;
                }),
                static::anything()
            );

        $this->service->assignCoverToVideo($videoId, $coverId, $this->context);
    }

    /**
     * @param array<string, mixed>|null $metaData
     */
    private function createMediaEntity(
        string $id,
        string $mimeType,
        MediaType $mediaType,
        ?array $metaData
    ): MediaEntity {
        $entity = new MediaEntity();
        $entity->setId($id);
        $entity->setMimeType($mimeType);
        $entity->setMediaType($mediaType);
        if ($metaData !== null) {
            $entity->setMetaData($metaData);
        }

        return $entity;
    }

    /**
     * @return EntitySearchResult<MediaCollection>
     */
    private function createSearchResult(?MediaEntity $entity): EntitySearchResult
    {
        $collection = new MediaCollection();
        if ($entity !== null) {
            $collection->add($entity);
        }

        return new EntitySearchResult(
            MediaDefinition::class,
            1,
            $collection,
            null,
            new Criteria(),
            $this->context
        );
    }
}
