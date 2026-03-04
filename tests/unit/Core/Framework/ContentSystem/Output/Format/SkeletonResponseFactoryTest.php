<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Output\Format;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Output\Format\SkeletonResponseFactory;
use Shopware\Core\Framework\ContentSystem\Output\Struct\ContentPage;
use Shopware\Core\Framework\ContentSystem\RenderingMode;
use Shopware\Core\Framework\ContentSystem\SalesChannel\ContentSkeletonRouteResponse;
use Shopware\Tests\Unit\Core\Framework\ContentSystem\_helper\ContentElementBuilder;

/**
 * @internal
 */
#[CoversClass(SkeletonResponseFactory::class)]
class SkeletonResponseFactoryTest extends TestCase
{
    #[TestDox('creates ContentSkeletonRouteResponse from content page')]
    public function testCreateResponseReturnsContentSkeletonRouteResponse(): void
    {
        $factory = new SkeletonResponseFactory();
        $root = ContentElementBuilder::create('section', 'r1')->build();
        $page = new ContentPage('layout-1', [$root], 'Test', null);

        $response = $factory->createResponse($page);

        static::assertInstanceOf(ContentSkeletonRouteResponse::class, $response);
        $skeletonPage = $response->getContentSkeletonPage();
        static::assertSame('layout-1', $skeletonPage->layoutId);
        static::assertCount(1, $skeletonPage->elements);
        static::assertSame('r1', $skeletonPage->elements[0]->id);
        static::assertSame('section', $skeletonPage->elements[0]->component);
    }

    #[TestDox('returns skeleton rendering mode')]
    public function testGetRenderingModeReturnsSkeleton(): void
    {
        $factory = new SkeletonResponseFactory();

        static::assertSame(RenderingMode::SKELETON, $factory->getRenderingMode());
    }
}
