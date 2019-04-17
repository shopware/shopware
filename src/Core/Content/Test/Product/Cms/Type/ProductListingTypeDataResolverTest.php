<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\Cms\Type;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ProductListingStruct;
use Shopware\Core\Content\Cms\SlotDataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\SlotDataResolver\SlotDataResolveResult;
use Shopware\Core\Content\Product\Cms\ListingGatewayInterface;
use Shopware\Core\Content\Product\Cms\Type\ProductListingTypeDataResolver;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class ProductListingTypeDataResolverTest extends TestCase
{
    /**
     * @var ProductListingTypeDataResolver
     */
    private $listingResolver;

    protected function setUp(): void
    {
        $mock = $this->createMock(ListingGatewayInterface::class);
        $mock->method('search')->willReturn(
            new EntitySearchResult(0, new EntityCollection(), null, new Criteria(), Context::createDefaultContext())
        );

        $this->listingResolver = new ProductListingTypeDataResolver($mock);
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
        $result = new SlotDataResolveResult();

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('product-listing');

        $this->listingResolver->enrich($slot, $resolverContext, $result);

        static::assertInstanceOf(ProductListingStruct::class, $slot->getData());
        static::assertInstanceOf(EntitySearchResult::class, $slot->getData()->getListing());
    }
}
