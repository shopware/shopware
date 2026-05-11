<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Aggregate\MediaThumbnail;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailEntity;

/**
 * @internal
 */
#[CoversClass(MediaThumbnailEntity::class)]
class MediaThumbnailStructTest extends TestCase
{
    public function testGetIdentifier(): void
    {
        $thumbnail = new MediaThumbnailEntity();
        $thumbnail->setWidth(120);
        $thumbnail->setHeight(100);

        static::assertSame('120x100', $thumbnail->getIdentifier());
    }
}
