<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewCollection;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewEntity;
use Shopware\Core\Content\Product\Exception\ReviewNotActiveExeption;
use Shopware\Core\Content\Product\Exception\VariantNotFoundException;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\FindVariant\FindProductVariantRoute;
use Shopware\Core\Content\Product\SalesChannel\FindVariant\FindProductVariantRouteResponse;
use Shopware\Core\Content\Product\SalesChannel\FindVariant\FoundCombination;
use Shopware\Core\Content\Product\SalesChannel\Review\AbstractProductReviewSaveRoute;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\NoContentResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Controller\Exception\StorefrontException;
use Shopware\Storefront\Controller\ProductController;
use Shopware\Storefront\Page\Product\ProductPage;
use Shopware\Storefront\Page\Product\ProductPageLoader;
use Shopware\Storefront\Page\Product\QuickView\MinimalQuickViewPage;
use Shopware\Storefront\Page\Product\QuickView\MinimalQuickViewPageLoader;
use Shopware\Storefront\Page\Product\QuickView\ProductQuickViewWidgetLoadedHook;
use Shopware\Storefront\Page\Product\Review\ProductReviewLoader;
use Shopware\Storefront\Page\Product\Review\ReviewLoaderResult;
use Shopware\Tests\Unit\Storefront\Controller\Stub\ProductControllerStub;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
#[CoversClass(ProductController::class)]
class ProductControllerTest extends TestCase
{
    private MockObject&ProductPageLoader $productPageLoaderMock;

    private MockObject&FindProductVariantRoute $findVariantRouteMock;

    private MockObject&SeoUrlPlaceholderHandlerInterface $seoUrlPlaceholderHandlerMock;

    private MockObject&MinimalQuickViewPageLoader $minimalQuickViewPageLoaderMock;

    private MockObject&AbstractProductReviewSaveRoute $productReviewSaveRouteMock;

    private MockObject&SystemConfigService $systemConfigServiceMock;

    private MockObject&ProductReviewLoader $productReviewLoaderMock;

    private ProductControllerStub $controller;

    protected function setUp(): void
    {
        $this->productPageLoaderMock = $this->createMock(ProductPageLoader::class);
        $this->findVariantRouteMock = $this->createMock(FindProductVariantRoute::class);
        $this->seoUrlPlaceholderHandlerMock = $this->createMock(SeoUrlPlaceholderHandlerInterface::class);
        $this->minimalQuickViewPageLoaderMock = $this->createMock(MinimalQuickViewPageLoader::class);
        $this->productReviewSaveRouteMock = $this->createMock(AbstractProductReviewSaveRoute::class);
        $this->systemConfigServiceMock = $this->createMock(SystemConfigService::class);
        $this->productReviewLoaderMock = $this->createMock(ProductReviewLoader::class);

        $this->controller = new ProductControllerStub(
            $this->productPageLoaderMock,
            $this->findVariantRouteMock,
            $this->minimalQuickViewPageLoaderMock,
            $this->productReviewSaveRouteMock,
            $this->seoUrlPlaceholderHandlerMock,
            $this->productReviewLoaderMock,
            $this->systemConfigServiceMock
        );
    }

    public function testIndexCmsPage(): void
    {
        $productEntity = new SalesChannelProductEntity();
        $productEntity->setId('test');
        $productPage = new ProductPage();
        $productPage->setProduct($productEntity);
        $productPage->setCmsPage(new CmsPageEntity());

        $this->productPageLoaderMock->method('load')->willReturn($productPage);

        $response = $this->controller->index($this->createMock(SalesChannelContext::class), new Request());

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertInstanceOf(ProductPage::class, $this->controller->renderStorefrontParameters['page']);
        static::assertSame('test', $this->controller->renderStorefrontParameters['page']->getProduct()->getId());
        static::assertSame('@Storefront/storefront/page/content/product-detail.html.twig', $this->controller->renderStorefrontView);
    }

    public function testSwitchNoVariantReturn(): void
    {
        $response = $this->controller->switch(Uuid::randomHex(), new Request(), $this->createMock(SalesChannelContext::class));

        static::assertSame('{"url":"","productId":""}', $response->getContent());
    }

    public function testSwitchVariantReturn(): void
    {
        $ids = new IdsCollection();

        $options = [
            $ids->get('group1') => $ids->get('option1'),
            $ids->get('group2') => $ids->get('option2'),
        ];

        $request = new Request(
            [
                'switched' => $ids->get('element'),
                'options' => json_encode($options, \JSON_THROW_ON_ERROR),
            ]
        );

        $expectedDuplicatedRequestData = [
            'options' => $options,
            'switchedGroup' => $ids->get('element'),
        ];
        $expectedClonedRequest = $request->duplicate($expectedDuplicatedRequestData);

        $this->findVariantRouteMock->method('load')
            ->with(
                $ids->get('product'),
                static::equalTo($expectedClonedRequest)
            )
            ->willReturn(
                new FindProductVariantRouteResponse(new FoundCombination($ids->get('variantId'), $options))
            );

        $this->seoUrlPlaceholderHandlerMock->method('generate')->with(
            'frontend.detail.page',
            ['productId' => $ids->get('variantId')]
        )->willReturn('https://test.com/test');

        $this->seoUrlPlaceholderHandlerMock->method('replace')->willReturnArgument(0);

        $response = $this->controller->switch($ids->get('product'), $request, $this->createMock(SalesChannelContext::class));

        static::assertSame('{"url":"https:\/\/test.com\/test","productId":"' . $ids->get('variantId') . '"}', $response->getContent());
    }

    public function testSwitchVariantException(): void
    {
        $ids = new IdsCollection();

        $options = [
            $ids->get('group1') => $ids->get('option1'),
            $ids->get('group2') => $ids->get('option2'),
        ];

        $this->findVariantRouteMock->method('load')->willThrowException(new VariantNotFoundException($ids->get('product'), $options));

        $response = $this->controller->switch($ids->get('product'), new Request(), $this->createMock(SalesChannelContext::class));

        static::assertSame('{"url":"","productId":"' . $ids->get('product') . '"}', $response->getContent());
    }

    public function testQuickViewMinimal(): void
    {
        $ids = new IdsCollection();

        $request = new Request(['productId' => $ids->get('productId')]);
        $this->minimalQuickViewPageLoaderMock->method('load')->with($request)->willReturn(new MinimalQuickViewPage(new ProductEntity()));

        $response = $this->controller->quickviewMinimal(
            $request,
            $this->createMock(SalesChannelContext::class)
        );

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertInstanceOf(MinimalQuickViewPage::class, $this->controller->renderStorefrontParameters['page']);
        static::assertInstanceOf(ProductQuickViewWidgetLoadedHook::class, $this->controller->calledHook);
    }

    public function testSaveReviewDeactivated(): void
    {
        $ids = new IdsCollection();

        $this->systemConfigServiceMock->method('get')->with('core.listing.showReview')->willReturn(false);

        $requestBag = new RequestDataBag(['test' => 'test']);

        if (Feature::isActive('v6.7.0.0')) {
            $this->expectException(StorefrontException::class);
        } else {
            $this->expectException(ReviewNotActiveExeption::class);
        }
        $this->expectExceptionMessage('Reviews not activated');

        $this->controller->saveReview(
            $ids->get('productId'),
            $requestBag,
            $this->createMock(SalesChannelContext::class)
        );
    }

    public function testSaveReview(): void
    {
        $ids = new IdsCollection();

        $this->systemConfigServiceMock->method('get')->with('core.listing.showReview')->willReturn(true);

        $requestBag = new RequestDataBag(['test' => 'test']);

        $this->productReviewSaveRouteMock->method('save')->with(
            $ids->get('productId'),
            $requestBag,
            $this->createMock(SalesChannelContext::class)
        )->willReturn(new NoContentResponse());

        $response = $this->controller->saveReview(
            $ids->get('productId'),
            $requestBag,
            $this->createMock(SalesChannelContext::class)
        );

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame('frontend.product.reviews', $this->controller->forwardToRoute);
        static::assertEquals(
            [
                'productId' => $ids->get('productId'),
                'success' => 1,
                'data' => $requestBag,
                'parentId' => null,
            ],
            $this->controller->forwardToRouteAttributes
        );
        static::assertSame(['productId' => $ids->get('productId')], $this->controller->forwardToRouteParameters);

        $requestBag->set('id', 'any');

        $response = $this->controller->saveReview(
            $ids->get('productId'),
            $requestBag,
            $this->createMock(SalesChannelContext::class)
        );

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame('frontend.product.reviews', $this->controller->forwardToRoute);
        static::assertEquals(
            [
                'productId' => $ids->get('productId'),
                'success' => 2,
                'data' => $requestBag,
                'parentId' => null,
            ],
            $this->controller->forwardToRouteAttributes
        );
    }

    public function testSaveReviewViolation(): void
    {
        $ids = new IdsCollection();

        $this->systemConfigServiceMock->method('get')->with('core.listing.showReview')->willReturn(true);

        $requestBag = new RequestDataBag(['test' => 'test']);

        $violations = new ConstraintViolationException(new ConstraintViolationList(), []);

        $this->productReviewSaveRouteMock->method('save')->willThrowException($violations);

        $response = $this->controller->saveReview(
            $ids->get('productId'),
            new RequestDataBag(['test' => 'test']),
            $this->createMock(SalesChannelContext::class)
        );

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame('frontend.product.reviews', $this->controller->forwardToRoute);
        static::assertEquals(
            [
                'productId' => $ids->get('productId'),
                'success' => -1,
                'data' => $requestBag,
                'formViolations' => $violations,
            ],
            $this->controller->forwardToRouteAttributes
        );
        static::assertSame(['productId' => $ids->get('productId')], $this->controller->forwardToRouteParameters);
    }

    public function testLoadReview(): void
    {
        $ids = new IdsCollection();

        $this->systemConfigServiceMock->method('get')->with('core.listing.showReview')->willReturn(true);

        $request = new Request(['test' => 'test']);

        $productReview = new ProductReviewEntity();
        $productReview->setUniqueIdentifier($ids->get('productReview'));
        $reviewResult = new ReviewLoaderResult(
            'review',
            1,
            new ProductReviewCollection([$productReview]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );
        $this->productReviewLoaderMock->method('load')->with(
            $request,
            $this->createMock(SalesChannelContext::class)
        )->willReturn($reviewResult);

        $response = $this->controller->loadReviews(
            $request,
            $this->createMock(SalesChannelContext::class)
        );

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame('storefront/component/review/review.html.twig', $this->controller->renderStorefrontView);
        static::assertEquals(
            [
                'reviews' => $reviewResult,
                'ratingSuccess' => null,
            ],
            $this->controller->renderStorefrontParameters
        );
    }
}
