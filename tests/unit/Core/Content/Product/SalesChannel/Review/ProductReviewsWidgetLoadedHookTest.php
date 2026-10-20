<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Review;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewCollection;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewEntity;
use Shopware\Core\Content\Product\SalesChannel\FindVariant\FindProductVariantRoute;
use Shopware\Core\Content\Product\SalesChannel\PurchaseLimit\AbstractProductPurchaseLimitRoute;
use Shopware\Core\Content\Product\SalesChannel\Review\AbstractProductReviewSaveRoute;
use Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewLoader;
use Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewResult;
use Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewsWidgetLoadedHook;
use Shopware\Core\Content\Product\SalesChannel\Review\RatingMatrix;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Storefront\Page\Product\ProductPageLoader;
use Shopware\Storefront\Page\Product\QuickView\MinimalQuickViewPageLoader;
use Shopware\Tests\Unit\Storefront\Controller\Stub\ProductControllerStub;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(ProductReviewsWidgetLoadedHook::class)]
class ProductReviewsWidgetLoadedHookTest extends TestCase
{
    private Stub&ProductReviewLoader $productReviewLoaderMock;

    private ProductControllerStub $controller;

    protected function setUp(): void
    {
        $this->productReviewLoaderMock = static::createStub(ProductReviewLoader::class);

        $this->controller = new ProductControllerStub(
            static::createStub(ProductPageLoader::class),
            static::createStub(FindProductVariantRoute::class),
            static::createStub(MinimalQuickViewPageLoader::class),
            static::createStub(AbstractProductReviewSaveRoute::class),
            static::createStub(SeoUrlPlaceholderHandlerInterface::class),
            $this->productReviewLoaderMock,
            static::createStub(AbstractProductPurchaseLimitRoute::class),
        );
    }

    public function testHookTriggeredWhenProductReviewsWidgetIsLoaded(): void
    {
        $ids = new IdsCollection();

        $productId = Uuid::randomHex();
        $parentId = Uuid::randomHex();

        $request = new Request([
            'test' => 'test',
            'productId' => $productId,
            'parentId' => $parentId,
        ]);

        $productReview = new ProductReviewEntity();
        $productReview->setUniqueIdentifier($ids->get('productReview'));
        $reviewResult = ProductReviewResult::fromSearchResult(
            new EntitySearchResult(
                'product_review',
                1,
                new ProductReviewCollection([$productReview]),
                null,
                new Criteria(),
                Context::createDefaultContext()
            ),
            new RatingMatrix([]),
            $productId,
            1,
            null,
            $parentId,
        );

        $this->productReviewLoaderMock->method('load')->willReturn($reviewResult);

        $this->controller->loadReviews(
            $productId,
            $request,
            static::createStub(SalesChannelContext::class)
        );

        static::assertInstanceOf(ProductReviewsWidgetLoadedHook::class, $this->controller->calledHook);

        $productReviewsWidgetLoadedHook = $this->controller->calledHook;

        static::assertSame($reviewResult, $productReviewsWidgetLoadedHook->getReviews());
    }
}
