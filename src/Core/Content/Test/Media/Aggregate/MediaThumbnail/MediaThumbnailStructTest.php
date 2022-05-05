<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media\Aggregate\MediaThumbnail;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailEntity;

/**
 * @internal
 */
class MediaThumbnailStructTest extends TestCase
{
    public function testGetIdentifier(): void
    {
        $thumbnail = new MediaThumbnailEntity();
        $thumbnail->setWidth(120);
        $thumbnail->setHeight(100);

        static::assertEquals('120x100', $thumbnail->getIdentifier());
    }
}
