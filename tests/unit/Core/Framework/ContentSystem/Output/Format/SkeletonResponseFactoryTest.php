<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Output\Format;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\LayoutReference;
use Shopware\Core\Framework\ContentSystem\Output\Format\SkeletonResponseFactory;
use Shopware\Core\Framework\ContentSystem\Output\RenderResult;
use Shopware\Core\Framework\ContentSystem\Rendering\RenderedElement;
use Shopware\Core\Framework\ContentSystem\RenderingMode;
use Shopware\Core\Framework\ContentSystem\SalesChannel\ContentSkeletonRouteResponse;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SkeletonResponseFactory::class)]
class SkeletonResponseFactoryTest extends TestCase
{
    #[TestDox('projects the rendered forest into the skeleton response')]
    public function testCreateResponseProjectsTheRenderedForest(): void
    {
        $factory = new SkeletonResponseFactory();

        $result = new RenderResult(
            [new RenderedElement('r1', 'section', ['background' => 'blue'])],
            LayoutReference::create('layout-1', 'Test', '3'),
            null,
        );

        $response = $factory->createResponse($result);

        static::assertInstanceOf(ContentSkeletonRouteResponse::class, $response);
        $skeletonPage = $response->getContentSkeletonPage();
        static::assertSame('layout-1', $skeletonPage->id);
        static::assertSame('Test', $skeletonPage->name);
        static::assertSame('3', $skeletonPage->version);
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

    #[TestDox('has no property values to index and asks for no value index')]
    public function testCollectsNoValueIndex(): void
    {
        static::assertFalse((new SkeletonResponseFactory())->collectsValueIndex());
    }

    #[TestDox('projects an empty rendered forest into an empty skeleton element list')]
    public function testCreateResponseProjectsAnEmptyForestAsEmptyElements(): void
    {
        $factory = new SkeletonResponseFactory();

        $result = new RenderResult(
            [],
            LayoutReference::create('layout-1', 'Test', null),
            null,
        );

        $response = $factory->createResponse($result);

        static::assertInstanceOf(ContentSkeletonRouteResponse::class, $response);
        static::assertCount(0, $response->getContentSkeletonPage()->elements);
    }
}
