<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media\Aggregate\MediaThumbnail;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailStruct;

class MediaThumbnailStructTest extends TestCase
{
    public function testGetIdentifierWithoutHighDpi(): void
    {
        $thumbnail = new MediaThumbnailStruct();
        $thumbnail->setWidth(120);
        $thumbnail->setHeight(100);
        $thumbnail->setHighDpi(false);

        static::assertEquals('120x100', $thumbnail->getIdentifier());
    }

    public function testGetIdentifierWithHighDpi(): void
    {
        $thumbnail = new MediaThumbnailStruct();
        $thumbnail->setWidth(120);
        $thumbnail->setHeight(100);
        $thumbnail->setHighDpi(true);

        static::assertEquals('120x100@2x', $thumbnail->getIdentifier());
    }
}
