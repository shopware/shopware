<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Controller;

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
use Shopware\Core\Framework\Script\Execution\Hook;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\NoContentResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Controller\ProductController;
use Shopware\Storefront\Page\Product\ProductPage;
use Shopware\Storefront\Page\Product\ProductPageLoader;
use Shopware\Storefront\Page\Product\QuickView\MinimalQuickViewPage;
use Shopware\Storefront\Page\Product\QuickView\MinimalQuickViewPageLoader;
use Shopware\Storefront\Page\Product\QuickView\ProductQuickViewWidgetLoadedHook;
use Shopware\Storefront\Page\Product\Review\ProductReviewLoader;
use Shopware\Storefront\Page\Product\Review\ReviewLoaderResult;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 *
 * @covers \Shopware\Storefront\Controller\ProductController
 */
class ProductControllerTest extends TestCase
{
    private MockObject&ProductPageLoader $productPageLoaderMock;

    private SalesChannelProductEntity $productEntity;

    private ProductPage $productPage;

    private MockObject&FindProductVariantRoute $findVariantRouteMock;

    private MockObject&SeoUrlPlaceholderHandlerInterface $seoUrlPlaceholderHandlerMock;

    private MockObject&MinimalQuickViewPageLoader $minimalQuickViewPageLoaderMock;

    private MockObject&AbstractProductReviewSaveRoute $productReviewSaveRouteMock;

    private MockObject&SystemConfigService $systemConfigServiceMock;

    private MockObject&ProductReviewLoader $productReviewLoaderMock;

    private ProductControllerTestClass $controller;

    public function setUp(): void
    {
        $this->productPageLoaderMock = $this->createMock(ProductPageLoader::class);
        $this->findVariantRouteMock = $this->createMock(FindProductVariantRoute::class);
        $this->seoUrlPlaceholderHandlerMock = $this->createMock(SeoUrlPlaceholderHandlerInterface::class);
        $this->minimalQuickViewPageLoaderMock = $this->createMock(MinimalQuickViewPageLoader::class);
        $this->productReviewSaveRouteMock = $this->createMock(AbstractProductReviewSaveRoute::class);
        $this->systemConfigServiceMock = $this->createMock(SystemConfigService::class);
        $this->productReviewLoaderMock = $this->createMock(ProductReviewLoader::class);

        $this->controller = new ProductControllerTestClass(
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
        $this->productEntity = new SalesChannelProductEntity();
        $this->productEntity->setId('test');
        $this->productPage = new ProductPage();
        $this->productPage->setProduct($this->productEntity);
        $this->productPage->setCmsPage(new CmsPageEntity());

        $this->productPageLoaderMock->method('load')->willReturn($this->productPage);

        $response = $this->controller->index($this->createMock(SalesChannelContext::class), new Request());

        static::assertEquals(Response::HTTP_OK, $response->getStatusCode());
        static::assertInstanceOf(ProductPage::class, $this->controller->renderStorefrontParameters['page']);
        static::assertEquals('test', $this->controller->renderStorefrontParameters['page']->getProduct()->getId());
        static::assertEquals('@Storefront/storefront/page/content/product-detail.html.twig', $this->controller->renderStorefrontView);
    }

    public function testIndexNoCmsPage(): void
    {
        Feature::skipTestIfActive('v6.5.0.0', $this);

        $this->productEntity = new SalesChannelProductEntity();
        $this->productEntity->setId('test');
        $this->productPage = new ProductPage();
        $this->productPage->setProduct($this->productEntity);

        $this->productPageLoaderMock->method('load')->willReturn($this->productPage);

        $response = $this->controller->index($this->createMock(SalesChannelContext::class), new Request());

        static::assertEquals(Response::HTTP_OK, $response->getStatusCode());
        static::assertInstanceOf(ProductPage::class, $this->controller->renderStorefrontParameters['page']);
        static::assertEquals('@Storefront/storefront/page/product-detail/index.html.twig', $this->controller->renderStorefrontView);
    }

    public function testSwitchNoVariantReturn(): void
    {
        $response = $this->controller->switch(Uuid::randomHex(), new Request(), $this->createMock(SalesChannelContext::class));

        static::assertEquals('{"url":"","productId":""}', $response->getContent());
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

        $this->findVariantRouteMock->method('load')->with(
            $ids->get('product'),
            new Request(
                [
                    'options' => $options,
                    'switchedGroup' => $ids->get('element'),
                ]
            )
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

        static::assertEquals('{"url":"https:\/\/test.com\/test","productId":"' . $ids->get('variantId') . '"}', $response->getContent());
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

        static::assertEquals('{"url":"","productId":"' . $ids->get('product') . '"}', $response->getContent());
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

        static::assertEquals(Response::HTTP_OK, $response->getStatusCode());
        static::assertInstanceOf(MinimalQuickViewPage::class, $this->controller->renderStorefrontParameters['page']);
        static::assertInstanceOf(ProductQuickViewWidgetLoadedHook::class, $this->controller->calledHook);
    }

    public function testSaveReviewDeactivated(): void
    {
        $ids = new IdsCollection();

        $this->systemConfigServiceMock->method('get')->with('core.listing.showReview')->willReturn(false);

        $requestBag = new RequestDataBag(['test' => 'test']);

        static::expectException(ReviewNotActiveExeption::class);

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

        static::assertEquals(Response::HTTP_OK, $response->getStatusCode());
        static::assertEquals('frontend.product.reviews', $this->controller->forwardToRoute);
        static::assertEquals(
            [
                'productId' => $ids->get('productId'),
                'success' => 1,
                'data' => $requestBag,
                'parentId' => null,
            ],
            $this->controller->forwardToRouteAttributes
        );
        static::assertEquals(
            [
                'productId' => $ids->get('productId'),
            ],
            $this->controller->forwardToRouteParameters
        );

        $requestBag->set('id', 'any');

        $response = $this->controller->saveReview(
            $ids->get('productId'),
            $requestBag,
            $this->createMock(SalesChannelContext::class)
        );

        static::assertEquals(Response::HTTP_OK, $response->getStatusCode());
        static::assertEquals('frontend.product.reviews', $this->controller->forwardToRoute);
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

        static::assertEquals(Response::HTTP_OK, $response->getStatusCode());
        static::assertEquals('frontend.product.reviews', $this->controller->forwardToRoute);
        static::assertEquals(
            [
                'productId' => $ids->get('productId'),
                'success' => -1,
                'data' => $requestBag,
                'formViolations' => $violations,
            ],
            $this->controller->forwardToRouteAttributes
        );
        static::assertEquals(
            [
                'productId' => $ids->get('productId'),
            ],
            $this->controller->forwardToRouteParameters
        );
    }

    public function testLoadReview(): void
    {
        $ids = new IdsCollection();

        $this->systemConfigServiceMock->method('get')->with('core.listing.showReview')->willReturn(true);

        $requestBag = new RequestDataBag(['test' => 'test']);
        $request = new Request(['test' => 'test']);

        $productReview = new ProductReviewEntity();
        $productReview->setUniqueIdentifier($ids->get('productReview'));
        $reviewResult = new ReviewLoaderResult(
            'review',
            1,
            new ProductReviewCollection([$productReview]),
            null,
            new Criteria(),
            $this->createMock(Context::class)
        );
        $this->productReviewLoaderMock->method('load')->with(
            $request,
            $this->createMock(SalesChannelContext::class)
        )->willReturn($reviewResult);

        $response = $this->controller->loadReviews(
            $request,
            $requestBag,
            $this->createMock(SalesChannelContext::class)
        );

        static::assertEquals(Response::HTTP_OK, $response->getStatusCode());
        static::assertEquals('storefront/page/product-detail/review/review.html.twig', $this->controller->renderStorefrontView);
        static::assertEquals(
            [
                'reviews' => $reviewResult,
                'ratingSuccess' => null,
            ],
            $this->controller->renderStorefrontParameters
        );
    }
}

/**
 * @internal
 */
class ProductControllerTestClass extends ProductController
{
    public string $renderStorefrontView;

    /**
     * @var array<mixed>
     */
    public array $renderStorefrontParameters;

    public Hook $calledHook;

    public string $forwardToRoute;

    /**
     * @var array<string, mixed>
     */
    public array $forwardToRouteAttributes;

    /**
     * @var array<string, mixed>
     */
    public array $forwardToRouteParameters;

    /**
     * @param array<string, mixed> $parameters
     */
    protected function renderStorefront(string $view, array $parameters = []): Response
    {
        $this->renderStorefrontView = $view;
        $this->renderStorefrontParameters = $parameters;

        return new Response();
    }

    /**
     * @param array<string, mixed> $attributes
     * @param array<string, mixed> $routeParameters
     */
    protected function forwardToRoute(string $routeName, array $attributes = [], array $routeParameters = []): Response
    {
        $this->forwardToRoute = $routeName;
        $this->forwardToRouteAttributes = $attributes;
        $this->forwardToRouteParameters = $routeParameters;

        return new Response();
    }

    protected function hook(Hook $hook): void
    {
        $this->calledHook = $hook;
    }
}
