<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media\Thumbnail;

use Shopware\Core\Content\Media\Thumbnail\ThumbnailConfiguration;

class ThumbnailConfigurationTest extends \PHPUnit\Framework\TestCase
{
    public function testGetSizeArray()
    {
        $sizeArray = ThumbnailConfiguration::getSizeArray(100, 200);
        static::assertEquals(100, $sizeArray['width']);
        static::assertEquals(200, $sizeArray['height']);
    }

    public function testGetDefaults()
    {
        $defaultConfiguration = ThumbnailConfiguration::getDefaultThumbnailConfiguration();

        static::assertEquals(90, $defaultConfiguration->getStandardQuality());
        static::assertFalse($defaultConfiguration->isHighDpi());
        static::assertTrue($defaultConfiguration->isAutoGenerateAfterUpload());
        static::assertTrue($defaultConfiguration->isKeepProportions());

        static::assertEquals(
            [
                ThumbnailConfiguration::getSizeArray(140, 140),
                ThumbnailConfiguration::getSizeArray(300, 300),
            ],
            $defaultConfiguration->getThumbnailSizes()
        );

        static::assertFalse($defaultConfiguration->isMimeTypeSupported('image/svg+xml'));
    }
}
