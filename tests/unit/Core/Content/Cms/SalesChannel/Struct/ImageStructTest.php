<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cms\SalesChannel\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ImageStruct;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ImageStruct::class)]
class ImageStructTest extends TestCase
{
    public function testApiAlias(): void
    {
        static::assertSame('cms_image', (new ImageStruct())->getApiAlias());
    }

    public function testMediaAccessors(): void
    {
        $media = new MediaEntity();
        $media->setUniqueIdentifier('media123');

        $struct = new ImageStruct();
        $struct->setMedia($media);
        $struct->setMediaId('media123');

        static::assertSame($media, $struct->getMedia());
        static::assertSame('media123', $struct->getMediaId());
    }

    public function testUrlAccessors(): void
    {
        $struct = new ImageStruct();
        $struct->setUrl('http://shopware.com/image.jpg');

        static::assertSame('http://shopware.com/image.jpg', $struct->getUrl());
    }

    public function testAriaLabelAccessors(): void
    {
        $struct = new ImageStruct();
        $struct->setAriaLabel('decorative image');

        static::assertSame('decorative image', $struct->getAriaLabel());
    }

    public function testNewTabAccessors(): void
    {
        $struct = new ImageStruct();
        $struct->setNewTab(true);

        static::assertTrue($struct->getNewTab());
    }
}
