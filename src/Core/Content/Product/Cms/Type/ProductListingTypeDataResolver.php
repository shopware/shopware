<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Cms\Type;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ProductListingStruct;
use Shopware\Core\Content\Cms\SlotDataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\SlotDataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\SlotDataResolver\SlotDataResolveResult;
use Shopware\Core\Content\Cms\SlotDataResolver\Type\TypeDataResolver;
use Shopware\Core\Content\Product\Cms\ListingGatewayInterface;

class ProductListingTypeDataResolver extends TypeDataResolver
{
    /**
     * @var ListingGatewayInterface
     */
    private $listingGateway;

    public function __construct(ListingGatewayInterface $listingGateway)
    {
        $this->listingGateway = $listingGateway;
    }

    public function getType(): string
    {
        return 'product-listing';
    }

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        return null;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, SlotDataResolveResult $result): void
    {
        $data = new ProductListingStruct();
        $slot->setData($data);

        $listing = $this->listingGateway->search($resolverContext->getRequest(), $resolverContext->getSalesChannelContext());

        $data->setListing($listing);
    }
}
