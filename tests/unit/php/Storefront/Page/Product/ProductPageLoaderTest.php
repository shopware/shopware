<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Page\Product;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockEntity;
use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionEntity;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Product\SalesChannel\CrossSelling\ProductCrossSellingRoute;
use Shopware\Core\Content\Product\SalesChannel\Detail\ProductDetailRoute;
use Shopware\Core\Content\Product\SalesChannel\Detail\ProductDetailRouteResponse;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\Tax\TaxCollection;
use Shopware\Storefront\Page\GenericPageLoader;
use Shopware\Storefront\Page\Product\ProductPageLoader;
use Shopware\Storefront\Page\Product\Review\ProductReviewLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 *
 * @covers \Shopware\Storefront\Page\Product\ProductPageLoader
 */
class ProductPageLoaderTest extends TestCase
{
    public function testItLoadsReviews(): void
    {
        $productId = Uuid::randomHex();
        $request = new Request([], [], ['productId' => $productId]);
        $salesChannelContext = $this->getSalesChannelContext('salesChannelId');
        $reviews = $this->getCmsSlotConfig();

        $productPageLoader = $this->getProductPageLoaderWithProduct($productId, $reviews, $request, $salesChannelContext);

        $page = $productPageLoader->load($request, $salesChannelContext);

        /** @phpstan-ignore-next-line $slot */
        $slot = $page->getCmsPage()->getSections()->first()->getBlocks()->first()->getSlots()->first()->getSlot();

        static::assertEquals($reviews, json_decode($slot, true));
    }

    /**
     * @param array<string, array<string, array<string, array<string, array<string, string>>>>> $reviews
     */
    private function getProductPageLoaderWithProduct(string $productId, array $reviews, Request $request, SalesChannelContext $salesChannelContext): ProductPageLoader
    {
        $product = $this->getProductWithReviews($productId, $reviews);

        // set cms page which later will be set by the subscriber
        $product->setCmsPage($this->getCmsPage($product));

        $criteria = (new Criteria())
            ->addAssociation('manufacturer.media')
            ->addAssociation('options.group')
            ->addAssociation('properties.group')
            ->addAssociation('mainCategories.category')
            ->addAssociation('media');

        $productDetailRouteMock = $this->createMock(ProductDetailRoute::class);
        $productDetailRouteMock
            ->method('load')
            ->with($productId, $request, $salesChannelContext, $criteria)
            ->willReturn(new ProductDetailRouteResponse($product, null));

        return new ProductPageLoader(
            $this->createMock(GenericPageLoader::class),
            $this->createMock(EventDispatcherInterface::class),
            $productDetailRouteMock,
            $this->createMock(ProductReviewLoader::class),
            $this->createMock(ProductCrossSellingRoute::class)
        );
    }

    /**
     * @param array<string, array<string, array<string, array<string, array<string, string>>>>> $reviews
     */
    private function getProductWithReviews(string $productId, array $reviews): SalesChannelProductEntity
    {
        $product = new SalesChannelProductEntity();
        $product->setId($productId);

        // set reviews
        $product->setTranslated($reviews);

        return $product;
    }

    private function getSalesChannelContext(string $salesChanelId): SalesChannelContext
    {
        $salesChannelEntity = new SalesChannelEntity();
        $salesChannelEntity->setId($salesChanelId);

        return new SalesChannelContext(
            Context::createDefaultContext(),
            'foo',
            'bar',
            $salesChannelEntity,
            new CurrencyEntity(),
            new CustomerGroupEntity(),
            new TaxCollection(),
            new PaymentMethodEntity(),
            new ShippingMethodEntity(),
            new ShippingLocation(new CountryEntity(), null, null),
            new CustomerEntity(),
            new CashRoundingConfig(2, 0.01, true),
            new CashRoundingConfig(2, 0.01, true),
            []
        );
    }

    private function getCmsPage(SalesChannelProductEntity $productEntity): CmsPageEntity
    {
        $cmsPageEntity = new CmsPageEntity();

        $cmsSectionEntity = new CmsSectionEntity();
        $cmsSectionEntity->setId(Uuid::randomHex());

        $cmsBlockEntity = new CmsBlockEntity();
        $cmsBlockEntity->setId(Uuid::randomHex());

        $cmsSlotEntity = new CmsSlotEntity();
        $cmsSlotEntity->setId(Uuid::randomHex());
        $cmsSlotEntity->setSlot(json_encode($productEntity->getTranslated(), \JSON_THROW_ON_ERROR));

        $cmsBlockEntity->setSlots(new CmsSlotCollection([$cmsSlotEntity]));
        $cmsSectionEntity->setBlocks(new CmsBlockCollection([$cmsBlockEntity]));
        $cmsPageEntity->setSections(new CmsSectionCollection([$cmsSectionEntity]));

        return $cmsPageEntity;
    }

    /**
     * @return array<string, array<string, array<string, array<string, array<string, string>>>>>
     */
    private function getCmsSlotConfig(): array
    {
        return [
            'data' => [
                'reviews' => [
                    'elements' => [
                        'myReviewElement' => [
                            'title' => 'myReviewTitle',
                            'content' => 'this product changed my life',
                        ],
                    ],
                ],
            ],
        ];
    }
}
