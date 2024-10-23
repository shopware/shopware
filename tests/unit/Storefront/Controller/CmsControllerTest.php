<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\SalesChannel\CategoryRoute;
use Shopware\Core\Content\Category\SalesChannel\CategoryRouteResponse;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Cms\Exception\PageNotFoundException;
use Shopware\Core\Content\Cms\SalesChannel\CmsRoute;
use Shopware\Core\Content\Cms\SalesChannel\CmsRouteResponse;
use Shopware\Core\Content\Product\SalesChannel\Detail\ProductDetailRoute;
use Shopware\Core\Content\Product\SalesChannel\FindVariant\FindProductVariantRoute;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingRoute;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingRouteResponse;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\CountResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\SumResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Storefront\Controller\CmsController;
use Shopware\Storefront\Page\Product\Review\ProductReviewLoader;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('buyers-experience')]
#[CoversClass(CmsController::class)]
class CmsControllerTest extends TestCase
{
    private MockObject&CmsRoute $cmsRouteMock;

    private MockObject&CategoryRoute $categoryRouteMock;

    private MockObject&ProductListingRoute $productListingRouteMock;

    private CmsControllerTestClass $controller;

    protected function setUp(): void
    {
        $eventDispatcherMock = $this->createMock(EventDispatcher::class);
        $this->cmsRouteMock = $this->createMock(CmsRoute::class);
        $this->categoryRouteMock = $this->createMock(CategoryRoute::class);
        $this->productListingRouteMock = $this->createMock(ProductListingRoute::class);

        $this->controller = new CmsControllerTestClass(
            $this->cmsRouteMock,
            $this->categoryRouteMock,
            $this->productListingRouteMock,
            $this->createMock(ProductDetailRoute::class),
            $this->createMock(ProductReviewLoader::class),
            $this->createMock(FindProductVariantRoute::class),
            $eventDispatcherMock
        );
    }

    public function testPageNoId(): void
    {
        $this->expectException(RoutingException::class);
        $this->expectExceptionMessage('Parameter "id" is missing.');

        $this->controller->page(null, new Request(), $this->createMock(SalesChannelContext::class));
    }

    public function testPageReturn(): void
    {
        $cmsRouteResponse = new CmsRouteResponse(new CmsPageEntity());
        $this->cmsRouteMock->method('load')->willReturn($cmsRouteResponse);

        $ids = new IdsCollection();

        $this->controller->page($ids->get('page'), new Request(), $this->createMock(SalesChannelContext::class));

        static::assertEquals($cmsRouteResponse->getCmsPage(), $this->controller->renderStorefrontParameters['cmsPage']);
    }

    public function testCategoryNoId(): void
    {
        $this->expectException(RoutingException::class);
        $this->expectExceptionMessage('Parameter "navigationId" is missing.');

        $this->controller->category(null, new Request(), $this->createMock(SalesChannelContext::class));
    }

    public function testCategoryReturn(): void
    {
        $categoryEntity = new CategoryEntity();
        $categoryEntity->setCmsPage(new CmsPageEntity());
        $categoryRouteResponse = new CategoryRouteResponse($categoryEntity);
        $this->categoryRouteMock->method('load')->willReturn($categoryRouteResponse);

        $ids = new IdsCollection();

        $this->controller->category($ids->get('category'), new Request(), $this->createMock(SalesChannelContext::class));

        static::assertEquals($categoryRouteResponse->getCategory()->getCmsPage(), $this->controller->renderStorefrontParameters['cmsPage']);
    }

    public function testCategoryPageNotFound(): void
    {
        $categoryEntity = new CategoryEntity();
        $categoryRouteResponse = new CategoryRouteResponse($categoryEntity);
        $this->categoryRouteMock->method('load')->willReturn($categoryRouteResponse);

        $ids = new IdsCollection();

        static::expectException(PageNotFoundException::class);

        $this->controller->category($ids->get('category'), new Request(), $this->createMock(SalesChannelContext::class));
    }

    public function testFilterReturn(): void
    {
        $ids = new IdsCollection();

        $testAggregations = new \ArrayObject([
            'count' => new CountResult('count', 2),
            'sum' => new SumResult('sum', 2.3),
        ]);
        $productListingResultMock = $this->createMock(ProductListingResult::class);
        $productListingResultMock->method('getAggregations')->willReturn(
            new AggregationResultCollection(
                $testAggregations
            )
        );

        $request = new Request();

        $productListingRouteResponse = new ProductListingRouteResponse($productListingResultMock);
        $this->productListingRouteMock->method('load')->willReturn($productListingRouteResponse);

        $response = $this->controller->filter($ids->get('navigation'), $request, $this->createMock(SalesChannelContext::class));

        static::assertEquals(
            json_encode($testAggregations, \JSON_THROW_ON_ERROR),
            json_encode(json_decode($response->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR), \JSON_THROW_ON_ERROR)
        );

        static::assertTrue($request->request->get('only-aggregations'));
        static::assertTrue($request->request->get('reduce-aggregations'));
    }

    public function testSwitchReturn(): void
    {
        $ids = new IdsCollection();

        $request = new Request(
            [
                'elementId' => $ids->get('element'),
                'options' => json_encode([
                    $ids->get('group1') => $ids->get('option1'),
                    $ids->get('group2') => $ids->get('option2'),
                ], \JSON_THROW_ON_ERROR),
            ]
        );

        $this->controller->switchBuyBoxVariant($ids->get('product'), $request, $this->createMock(SalesChannelContext::class));

        static::assertInstanceOf(SalesChannelProductEntity::class, $this->controller->renderStorefrontParameters['product']);

        static::assertEquals(
            $this->controller->renderStorefrontParameters,
            [
                'product' => $this->controller->renderStorefrontParameters['product'],
                'configuratorSettings' => null,
                'totalReviews' => 0,
                'elementId' => $ids->get('element'),
            ]
        );
    }

    public function testSwitchBuyBoxVariantWithInvalidJsonOptions(): void
    {
        $ids = new IdsCollection();

        $request = new Request(
            [
                'elementId' => $ids->get('element'),
                'options' => 'invalidJsonString',
            ]
        );

        $response = $this->controller->switchBuyBoxVariant(
            $ids->get('product'),
            $request,
            $this->createMock(SalesChannelContext::class)
        );

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }
}

/**
 * @internal
 */
class CmsControllerTestClass extends CmsController
{
    use StorefrontControllerMockTrait;
}
