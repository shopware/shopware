<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\Output\Format;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\Output\Format\SkeletonResponseFactory;
use Shopware\Core\Content\ContentSystem\Output\Struct\ContentPage;
use Shopware\Core\Content\ContentSystem\RenderingMode;
use Shopware\Core\Content\ContentSystem\SalesChannel\ContentSkeletonRouteResponse;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\Content\ContentSystem\_helper\ContentElementBuilder;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SkeletonResponseFactory::class)]
class SkeletonResponseFactoryTest extends TestCase
{
    #[TestDox('returns SKELETON rendering mode')]
    public function testGetRenderingModeReturnsSkeleton(): void
    {
        $factory = new SkeletonResponseFactory();

        static::assertSame(RenderingMode::SKELETON, $factory->getRenderingMode());
    }

    #[TestDox('creates ContentSkeletonRouteResponse from content page')]
    public function testCreateResponseReturnsContentSkeletonRouteResponse(): void
    {
        $factory = new SkeletonResponseFactory();
        $root = ContentElementBuilder::create('section', 'r1')->build();
        $page = new ContentPage('layout-1', [$root], 'Test', null);

        $response = $factory->createResponse($page);

        static::assertInstanceOf(ContentSkeletonRouteResponse::class, $response);
    }
}
