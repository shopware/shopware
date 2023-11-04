<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\Cms\Type;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ProductListingStruct;
use Shopware\Core\Content\Product\Cms\ProductListingCmsElementResolver;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingRoute;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingRouteResponse;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class ProductListingTypeDataResolverTest extends TestCase
{
    private ProductListingCmsElementResolver $listingResolver;

    protected function setUp(): void
    {
        $mock = $this->createMock(ProductListingRoute::class);
        $mock->method('load')->willReturn(
            new ProductListingRouteResponse(
                new ProductListingResult('product', 0, new EntityCollection(), null, new Criteria(), Context::createDefaultContext())
            )
        );

        $this->listingResolver = new ProductListingCmsElementResolver($mock);
    }

    public function testGetType(): void
    {
        static::assertEquals('product-listing', $this->listingResolver->getType());
    }

    public function testCollect(): void
    {
        $resolverContext = new ResolverContext($this->createMock(SalesChannelContext::class), new Request());

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('product-listing');

        $collection = $this->listingResolver->collect($slot, $resolverContext);

        static::assertNull($collection);
    }

    public function testEnrichWithoutListingContext(): void
    {
        $resolverContext = new ResolverContext($this->createMock(SalesChannelContext::class), new Request());
        $result = new ElementDataCollection();

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('product-listing');

        $this->listingResolver->enrich($slot, $resolverContext, $result);

        /** @var ProductListingStruct|null $productListingStruct */
        $productListingStruct = $slot->getData();
        static::assertInstanceOf(ProductListingStruct::class, $productListingStruct);
        static::assertInstanceOf(EntitySearchResult::class, $productListingStruct->getListing());
    }
}
