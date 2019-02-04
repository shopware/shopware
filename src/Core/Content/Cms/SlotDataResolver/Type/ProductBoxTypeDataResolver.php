<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\SlotDataResolver\Type;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\SlotDataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\SlotDataResolver\SlotDataResolveResult;
use Shopware\Core\Content\Cms\SlotDataResolver\SlotTypeDataResolverInterface;
use Shopware\Core\Content\Cms\Storefront\Struct\ProductBoxStruct;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\Storefront\StorefrontProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\InternalRequest;

class ProductBoxTypeDataResolver implements SlotTypeDataResolverInterface
{
    public function getType(): string
    {
        return 'product-box';
    }

    public function collect(CmsSlotEntity $slot, InternalRequest $request, CheckoutContext $context): ?CriteriaCollection
    {
        $config = $slot->getConfig();

        if (!isset($config['productId'])) {
            return null;
        }

        $criteria = new Criteria([$config['productId']]);

        $criteriaCollection = new CriteriaCollection();
        $criteriaCollection->add('product', ProductDefinition::class, $criteria);

        return $criteriaCollection;
    }

    public function enrich(CmsSlotEntity $slot, InternalRequest $request, CheckoutContext $context, SlotDataResolveResult $result): CmsSlotEntity
    {
        $config = $slot->getConfig();
        $productBox = ProductBoxStruct::createFrom($slot);

        $searchResult = $result->get('product');
        if (!$searchResult) {
            return $productBox;
        }

        /** @var StorefrontProductEntity|null $product */
        $product = $searchResult->get($config['productId']);
        if (!$product) {
            return $productBox;
        }

        $productBox->setProduct($product);
        $productBox->setProductId($config['productId']);

        return $productBox;
    }
}
