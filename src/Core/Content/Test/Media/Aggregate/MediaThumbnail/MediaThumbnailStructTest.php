<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media\Aggregate\MediaThumbnail;

use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailStruct;

class MediaThumbnailStructTest extends \PHPUnit\Framework\TestCase
{
    public function testGetIdentifierWithoutHighDpi(): void
    {
        $thumbnail = new MediaThumbnailStruct();
        $thumbnail->setWidth(120);
        $thumbnail->setHeight(100);
        $thumbnail->setHighDpi(false);

        $this->assertEquals('120x100', $thumbnail->getIdentifier());
    }

    public function testGetIdentifierWithHighDpi(): void
    {
        $thumbnail = new MediaThumbnailStruct();
        $thumbnail->setWidth(120);
        $thumbnail->setHeight(100);
        $thumbnail->setHighDpi(true);

        $this->assertEquals('120x100@2x', $thumbnail->getIdentifier());
    }
}
