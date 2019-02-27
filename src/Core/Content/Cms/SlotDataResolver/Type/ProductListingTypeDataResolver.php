<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\SlotDataResolver\Type;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\SlotDataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\SlotDataResolver\SlotDataResolveResult;
use Shopware\Core\Content\Cms\SlotDataResolver\SlotTypeDataResolverInterface;
use Shopware\Core\Content\Cms\Storefront\Struct\ProductListingStruct;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\InternalRequest;

class ProductListingTypeDataResolver implements SlotTypeDataResolverInterface
{
    public function getType(): string
    {
        return 'product-listing';
    }

    public function collect(CmsSlotEntity $slot, InternalRequest $request, CheckoutContext $context): CriteriaCollection
    {
        $collection = new CriteriaCollection();

        $criteria = new Criteria();
        $criteria->setLimit(10);

        $collection->add('listing', ProductDefinition::class, $criteria);

        return $collection;
    }

    public function enrich(CmsSlotEntity $slot, InternalRequest $request, CheckoutContext $context, SlotDataResolveResult $result): void
    {
        $data = new ProductListingStruct();
        $data->setSearchResult($result->get('listing'));

        $slot->setData($data);
    }
}
