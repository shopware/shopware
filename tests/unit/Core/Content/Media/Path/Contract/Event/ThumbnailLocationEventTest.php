<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Path\Contract\Event;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Path\Domain\Event\ThumbnailLocationEvent;
use Shopware\Core\Content\Media\Path\Domain\Struct\MediaLocationStruct;
use Shopware\Core\Content\Media\Path\Domain\Struct\ThumbnailLocationStruct;

/**
 * @internal
 *
 * @covers \Shopware\Core\Content\Media\Path\Domain\Event\ThumbnailLocationEvent
 */
class ThumbnailLocationEventTest extends TestCase
{
    public function testGetIterator(): void
    {
        $media = new MediaLocationStruct('foo', 'foo', 'foo', null);

        $locations = [
            'foo' => new ThumbnailLocationStruct('foo', 100, 101, $media),
            'bar' => new ThumbnailLocationStruct('bar', 100, 101, $media),
        ];

        $event = new ThumbnailLocationEvent($locations);

        static::assertSame($locations, iterator_to_array($event->getIterator()));
    }
}
