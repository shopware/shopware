<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Cms\SlotDataResolver\Type;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ProductListingStruct;
use Shopware\Core\Content\Cms\SlotDataResolver\ResolverContext\ListingResolverContext;
use Shopware\Core\Content\Cms\SlotDataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\SlotDataResolver\SlotDataResolveResult;
use Shopware\Core\Content\Cms\SlotDataResolver\Type\ProductListingTypeDataResolver;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ProductListingTypeDataResolverTest extends TestCase
{
    /**
     * @var ProductListingTypeDataResolver
     */
    private $listingResolver;

    protected function setUp(): void
    {
        $this->listingResolver = new ProductListingTypeDataResolver();
    }

    public function testGetType(): void
    {
        static::assertEquals('product-listing', $this->listingResolver->getType());
    }

    public function testCollect(): void
    {
        $resolverContext = new ResolverContext($this->createMock(SalesChannelContext::class));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('product-listing');

        $collection = $this->listingResolver->collect($slot, $resolverContext);

        static::assertNull($collection);
    }

    public function testEnrichWithoutListingContext(): void
    {
        $resolverContext = new ResolverContext($this->createMock(SalesChannelContext::class));
        $result = new SlotDataResolveResult();

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('product-listing');

        $this->listingResolver->enrich($slot, $resolverContext, $result);

        static::assertInstanceOf(ProductListingStruct::class, $slot->getData());
        static::assertNull($slot->getData()->getSearchResult());
    }

    public function testEnrichWithListingContext(): void
    {
        $product = new SalesChannelProductEntity();
        $product->setUniqueIdentifier('product1');

        $searchResult = new EntitySearchResult(
            1,
            new ProductCollection([$product]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );

        $resolverContext = new ListingResolverContext(
            $this->createMock(SalesChannelContext::class),
            ProductDefinition::class,
            $searchResult
        );

        $result = new SlotDataResolveResult();

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('product-listing');

        $this->listingResolver->enrich($slot, $resolverContext, $result);

        static::assertInstanceOf(ProductListingStruct::class, $slot->getData());
        static::assertSame($searchResult, $slot->getData()->getSearchResult());
    }
}
