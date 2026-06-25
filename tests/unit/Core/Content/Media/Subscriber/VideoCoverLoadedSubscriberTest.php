<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaEvents;
use Shopware\Core\Content\Media\Subscriber\VideoCoverLoadedSubscriber;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(VideoCoverLoadedSubscriber::class)]
class VideoCoverLoadedSubscriberTest extends TestCase
{
    /**
     * @var EntityRepository<MediaCollection>&MockObject
     */
    private EntityRepository $mediaRepository;

    private VideoCoverLoadedSubscriber $subscriber;

    private Context $context;

    protected function setUp(): void
    {
        $this->mediaRepository = $this->createMock(EntityRepository::class);
        $this->subscriber = new VideoCoverLoadedSubscriber($this->mediaRepository);
        $this->context = Context::createDefaultContext();
    }

    public function testSubscribesToCorrectEvent(): void
    {
        static::assertSame(
            [MediaEvents::MEDIA_LOADED_EVENT => 'addVideoCoverExtension'],
            VideoCoverLoadedSubscriber::getSubscribedEvents()
        );
    }

    public function testAddsVideoCoverExtensionWhenCoverExists(): void
    {
        $videoId = Uuid::randomHex();
        $coverId = Uuid::randomHex();

        $video = $this->createMediaEntity($videoId, ['video' => ['coverMediaId' => $coverId]]);
        $cover = $this->createMediaEntity($coverId, null);

        $event = new EntityLoadedEvent(
            new MediaDefinition(),
            [$video],
            $this->context
        );

        $this->mediaRepository
            ->expects($this->once())
            ->method('search')
            ->with(
                static::callback(static function (Criteria $criteria) use ($coverId) {
                    static::assertSame([$coverId], $criteria->getIds());
                    static::assertTrue($criteria->hasAssociation('thumbnails'));

                    return true;
                }),
                static::identicalTo($this->context)
            )
            ->willReturn($this->createSearchResult($cover));

        $this->subscriber->addVideoCoverExtension($event);

        static::assertTrue($video->hasExtension('videoCoverMedia'));
        static::assertSame($cover, $video->getExtension('videoCoverMedia'));
    }

    public function testDoesNothingWhenNoCoverMediaId(): void
    {
        $videoId = Uuid::randomHex();
        $video = $this->createMediaEntity($videoId, null);

        $event = new EntityLoadedEvent(
            new MediaDefinition(),
            [$video],
            $this->context
        );

        $this->mediaRepository
            ->expects($this->never())
            ->method('search');

        $this->subscriber->addVideoCoverExtension($event);

        static::assertFalse($video->hasExtension('videoCoverMedia'));
    }

    public function testHandlesMultipleVideosWithSameCover(): void
    {
        $video1Id = Uuid::randomHex();
        $video2Id = Uuid::randomHex();
        $coverId = Uuid::randomHex();

        $video1 = $this->createMediaEntity($video1Id, ['video' => ['coverMediaId' => $coverId]]);
        $video2 = $this->createMediaEntity($video2Id, ['video' => ['coverMediaId' => $coverId]]);
        $cover = $this->createMediaEntity($coverId, null);

        $event = new EntityLoadedEvent(
            new MediaDefinition(),
            [$video1, $video2],
            $this->context
        );

        $this->mediaRepository
            ->expects($this->once())
            ->method('search')
            ->willReturn($this->createSearchResult($cover));

        $this->subscriber->addVideoCoverExtension($event);

        static::assertTrue($video1->hasExtension('videoCoverMedia'));
        static::assertTrue($video2->hasExtension('videoCoverMedia'));
        static::assertSame($cover, $video1->getExtension('videoCoverMedia'));
        static::assertSame($cover, $video2->getExtension('videoCoverMedia'));
    }

    public function testHandlesMultipleVideosWithDifferentCovers(): void
    {
        $video1Id = Uuid::randomHex();
        $video2Id = Uuid::randomHex();
        $cover1Id = Uuid::randomHex();
        $cover2Id = Uuid::randomHex();

        $video1 = $this->createMediaEntity($video1Id, ['video' => ['coverMediaId' => $cover1Id]]);
        $video2 = $this->createMediaEntity($video2Id, ['video' => ['coverMediaId' => $cover2Id]]);
        $cover1 = $this->createMediaEntity($cover1Id, null);
        $cover2 = $this->createMediaEntity($cover2Id, null);

        $event = new EntityLoadedEvent(
            new MediaDefinition(),
            [$video1, $video2],
            $this->context
        );

        $this->mediaRepository
            ->expects($this->once())
            ->method('search')
            ->with(
                static::callback(static function (Criteria $criteria) use ($cover1Id, $cover2Id) {
                    $ids = $criteria->getIds();
                    static::assertCount(2, $ids);
                    static::assertContains($cover1Id, $ids);
                    static::assertContains($cover2Id, $ids);

                    return true;
                }),
                static::identicalTo($this->context)
            )
            ->willReturn($this->createSearchResult($cover1, $cover2));

        $this->subscriber->addVideoCoverExtension($event);

        static::assertTrue($video1->hasExtension('videoCoverMedia'));
        static::assertTrue($video2->hasExtension('videoCoverMedia'));
        static::assertSame($cover1, $video1->getExtension('videoCoverMedia'));
        static::assertSame($cover2, $video2->getExtension('videoCoverMedia'));
    }

    public function testSkipsVideoWhenCoverNotFound(): void
    {
        $videoId = Uuid::randomHex();
        $coverId = Uuid::randomHex();

        $video = $this->createMediaEntity($videoId, ['video' => ['coverMediaId' => $coverId]]);

        $event = new EntityLoadedEvent(
            new MediaDefinition(),
            [$video],
            $this->context
        );

        $this->mediaRepository
            ->expects($this->once())
            ->method('search')
            ->willReturn($this->createSearchResult());

        $this->subscriber->addVideoCoverExtension($event);

        static::assertFalse($video->hasExtension('videoCoverMedia'));
    }

    /**
     * @param array<string, mixed>|null $metaData
     */
    private function createMediaEntity(string $id, ?array $metaData): MediaEntity
    {
        $entity = new MediaEntity();
        $entity->setId($id);
        if ($metaData !== null) {
            $entity->setMetaData($metaData);
        }

        return $entity;
    }

    /**
     * @return EntitySearchResult<MediaCollection>
     */
    private function createSearchResult(MediaEntity ...$entities): EntitySearchResult
    {
        $collection = new MediaCollection($entities);

        return new EntitySearchResult(
            MediaDefinition::class,
            \count($entities),
            $collection,
            null,
            new Criteria(),
            $this->context
        );
    }
}
