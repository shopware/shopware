<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\SalesChannel\MediaRouteResponse;

/**
 * @internal
 */
#[CoversClass(MediaRouteResponse::class)]
class MediaRouteResponseTest extends TestCase
{
    public function testMediaRouterIsCorrectlyConstructed(): void
    {
        $mediaEntity = new MediaEntity();
        $mediaEntity->setId('testMediaId');
        $mediaEntity->setPath('testPath');

        $mediaCollection = new MediaCollection();
        $mediaCollection->add($mediaEntity);

        $mediaRouteResponse = new MediaRouteResponse($mediaCollection);

        static::assertSame($mediaCollection, $mediaRouteResponse->getMediaCollection());
    }
}
