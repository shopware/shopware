<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Review;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewCollection;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewEntity;
use Shopware\Core\Content\Product\SalesChannel\FindVariant\FindProductVariantRoute;
use Shopware\Core\Content\Product\SalesChannel\Review\AbstractProductReviewSaveRoute;
use Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewLoader;
use Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewResult;
use Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewsWidgetLoadedHook;
use Shopware\Core\Content\Product\SalesChannel\Review\RatingMatrix;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Page\Product\ProductPageLoader;
use Shopware\Storefront\Page\Product\QuickView\MinimalQuickViewPageLoader;
use Shopware\Tests\Unit\Storefront\Controller\Stub\ProductControllerStub;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductReviewsWidgetLoadedHook::class)]
class ProductReviewsWidgetLoadedHookTest extends TestCase
{
    private MockObject&ProductPageLoader $productPageLoaderMock;

    private MockObject&FindProductVariantRoute $findVariantRouteMock;

    private MockObject&SeoUrlPlaceholderHandlerInterface $seoUrlPlaceholderHandlerMock;

    private MockObject&MinimalQuickViewPageLoader $minimalQuickViewPageLoaderMock;

    private MockObject&AbstractProductReviewSaveRoute $productReviewSaveRouteMock;

    private MockObject&SystemConfigService $systemConfigServiceMock;

    private MockObject&ProductReviewLoader $productReviewLoaderMock;

    private MockObject&EventDispatcher $eventDispatcherMock;

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
        $this->eventDispatcherMock = $this->createMock(EventDispatcher::class);

        $this->controller = new ProductControllerStub(
            $this->productPageLoaderMock,
            $this->findVariantRouteMock,
            $this->minimalQuickViewPageLoaderMock,
            $this->productReviewSaveRouteMock,
            $this->seoUrlPlaceholderHandlerMock,
            $this->productReviewLoaderMock,
            $this->systemConfigServiceMock,
            $this->eventDispatcherMock,
        );
    }

    public function testHookTriggeredWhenProductReviewsWidgetIsLoaded(): void
    {
        Feature::skipTestIfInActive('v6.7.0.0', $this);

        $ids = new IdsCollection();

        $this->systemConfigServiceMock->method('get')->with('core.listing.showReview')->willReturn(true);

        $productId = Uuid::randomHex();
        $parentId = Uuid::randomHex();

        $request = new Request([
            'test' => 'test',
            'productId' => $productId,
            'parentId' => $parentId,
        ]);

        $productReview = new ProductReviewEntity();
        $productReview->setUniqueIdentifier($ids->get('productReview'));
        $reviewResult = new ProductReviewResult(
            'review',
            1,
            new ProductReviewCollection([$productReview]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );
        $reviewResult->setMatrix(new RatingMatrix([]));
        $reviewResult->setProductId($productId);
        $reviewResult->setParentId($parentId);

        $this->productReviewLoaderMock->method('load')->with(
            $request,
            $this->createMock(SalesChannelContext::class),
            $productId,
            $parentId
        )->willReturn($reviewResult);

        $this->controller->loadReviews(
            $productId,
            $request,
            $this->createMock(SalesChannelContext::class)
        );

        static::assertInstanceOf(ProductReviewsWidgetLoadedHook::class, $this->controller->calledHook);

        /** @var ProductReviewsWidgetLoadedHook $productReviewsWidgetLoadedHook */
        $productReviewsWidgetLoadedHook = $this->controller->calledHook;

        static::assertEquals($reviewResult, $productReviewsWidgetLoadedHook->getReviews());
    }
}
