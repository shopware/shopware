<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Thumbnail;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Thumbnail\ExternalThumbnailCollection;
use Shopware\Core\Content\Media\Thumbnail\ExternalThumbnailData;
use Shopware\Core\Content\Media\Thumbnail\ExternalThumbnailsParameters;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ExternalThumbnailsParameters::class)]
class ExternalThumbnailsParametersTest extends TestCase
{
    public function testDefaultsToEmptyCollection(): void
    {
        $params = new ExternalThumbnailsParameters();

        static::assertCount(0, $params->thumbnails);
    }

    public function testConstructWithThumbnails(): void
    {
        $thumbnail = new ExternalThumbnailData('http://localhost:8000/thumb-200.jpg', 200, 200);
        $collection = new ExternalThumbnailCollection([$thumbnail]);

        $params = new ExternalThumbnailsParameters($collection);

        static::assertCount(1, $params->thumbnails);
        static::assertSame($thumbnail, $params->thumbnails->first());
    }
}
