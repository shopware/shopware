<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Event\MediaPathChangedEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(MediaPathChangedEvent::class)]
class MediaPathChangedEventTest extends TestCase
{
    public function testMediaWithMimeTypeRecordsAChangeWithoutThumbnail(): void
    {
        $event = new MediaPathChangedEvent(Context::createDefaultContext());

        $event->mediaWithMimeType('media-id', 'media/path.png', 'image/png');

        static::assertSame([
            ['mediaId' => 'media-id', 'thumbnailId' => null, 'path' => 'media/path.png', 'mimeType' => 'image/png'],
        ], $event->changed);
    }

    public function testThumbnailWithMimeTypeRecordsAChangeWithThumbnail(): void
    {
        $event = new MediaPathChangedEvent(Context::createDefaultContext());

        $event->thumbnailWithMimeType('media-id', 'thumbnail-id', 'thumbnail/path.png');

        static::assertSame([
            ['mediaId' => 'media-id', 'thumbnailId' => 'thumbnail-id', 'path' => 'thumbnail/path.png', 'mimeType' => null],
        ], $event->changed);
    }

    /**
     * @deprecated tag:v6.8.0 - Covers the deprecated forwarders, will be removed with them
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testDeprecatedMethodsForwardWithoutMimeType(): void
    {
        $event = new MediaPathChangedEvent(Context::createDefaultContext());

        $event->media('media-id', 'media/path.png');
        $event->thumbnail('media-id', 'thumbnail-id', 'thumbnail/path.png');

        static::assertSame([
            ['mediaId' => 'media-id', 'thumbnailId' => null, 'path' => 'media/path.png', 'mimeType' => null],
            ['mediaId' => 'media-id', 'thumbnailId' => 'thumbnail-id', 'path' => 'thumbnail/path.png', 'mimeType' => null],
        ], $event->changed);
    }
}
