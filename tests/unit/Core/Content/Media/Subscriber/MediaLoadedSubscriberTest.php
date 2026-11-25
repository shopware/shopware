<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailCollection;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaType\ImageType;
use Shopware\Core\Content\Media\Subscriber\MediaLoadedSubscriber;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\PartialEntityLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(MediaLoadedSubscriber::class)]
class MediaLoadedSubscriberTest extends TestCase
{
    private MediaLoadedSubscriber $subscriber;

    private Context $context;

    protected function setUp(): void
    {
        $this->subscriber = new MediaLoadedSubscriber();
        $this->context = Context::createDefaultContext();
    }

    public function testRestoresMediaTypeAndThumbnailsOnFullEntity(): void
    {
        $media = new MediaEntity();
        $media->setId(Uuid::randomHex());
        $media->setMediaTypeRaw(serialize(new ImageType()));

        $this->subscriber->unserialize(new EntityLoadedEvent(new MediaDefinition(), [$media], $this->context));

        static::assertInstanceOf(ImageType::class, $media->getMediaType());
        static::assertInstanceOf(MediaThumbnailCollection::class, $media->getThumbnails());
        static::assertCount(0, $media->getThumbnails());
    }

    public function testRestoresMediaTypeAndThumbnailsOnPartialEntity(): void
    {
        $media = new PartialEntity();
        $media->assign([
            'id' => Uuid::randomHex(),
            'mediaTypeRaw' => serialize(new ImageType()),
        ]);

        $this->subscriber->unserialize(new PartialEntityLoadedEvent(new MediaDefinition(), [$media], $this->context));

        static::assertInstanceOf(ImageType::class, $media->get('mediaType'));
        static::assertInstanceOf(MediaThumbnailCollection::class, $media->get('thumbnails'));
        static::assertCount(0, $media->get('thumbnails'));
    }

    public function testKeepsAlreadyLoadedThumbnailsOnPartialEntity(): void
    {
        $thumbnails = new MediaThumbnailCollection();

        $media = new PartialEntity();
        $media->assign([
            'id' => Uuid::randomHex(),
            'thumbnails' => $thumbnails,
        ]);

        $this->subscriber->unserialize(new PartialEntityLoadedEvent(new MediaDefinition(), [$media], $this->context));

        static::assertSame($thumbnails, $media->get('thumbnails'));
    }

    public function testReadsProjectedThumbnailOnFullMedia(): void
    {
        $id = Uuid::randomHex();
        $media = new MediaEntity();
        $media->setThumbnailsRo([[
            'id' => $id,
            'width' => 200,
            'path' => 'thumbnail/cover.jpg',
            'createdAt' => '2026-01-01',
            'updatedAt' => null,
        ]]);

        $this->subscriber->unserialize(new EntityLoadedEvent(new MediaDefinition(), [$media], $this->context));

        $thumbnail = $media->getThumbnails()?->get($id);
        static::assertNotNull($thumbnail);
        static::assertSame('thumbnail/cover.jpg', $thumbnail->getPath());
        static::assertSame('2026-01-01', $thumbnail->getCreatedAt()?->format('Y-m-d'));
        static::assertNull($thumbnail->getUpdatedAt());
    }

    public function testDoesNotSetMediaTypeWhenRawIsMissingOnPartialEntity(): void
    {
        $media = new PartialEntity();
        $media->assign(['id' => Uuid::randomHex()]);

        $this->subscriber->unserialize(new PartialEntityLoadedEvent(new MediaDefinition(), [$media], $this->context));

        static::assertFalse($media->has('mediaType'));
        static::assertInstanceOf(MediaThumbnailCollection::class, $media->get('thumbnails'));
    }
}
