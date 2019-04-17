<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Cms\Type;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ProductBoxStruct;
use Shopware\Core\Content\Cms\SlotDataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\SlotDataResolver\ResolverContext\EntityResolverContext;
use Shopware\Core\Content\Cms\SlotDataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\SlotDataResolver\SlotDataResolveResult;
use Shopware\Core\Content\Cms\SlotDataResolver\Type\TypeDataResolver;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

class ProductBoxTypeDataResolver extends TypeDataResolver
{
    public function getType(): string
    {
        return 'product-box';
    }

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        $config = $slot->getFieldConfig();
        $productConfig = $config->get('product');

        if (!$productConfig || $productConfig->isMapped()) {
            return null;
        }

        $criteria = new Criteria([$productConfig->getValue()]);

        $criteriaCollection = new CriteriaCollection();
        $criteriaCollection->add('product_' . $slot->getUniqueIdentifier(), ProductDefinition::class, $criteria);

        return $criteriaCollection;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, SlotDataResolveResult $result): void
    {
        $productBox = new ProductBoxStruct();
        $slot->setData($productBox);

        $config = $slot->getFieldConfig();
        $productConfig = $config->get('product');

        if (!$productConfig) {
            return;
        }

        if ($resolverContext instanceof EntityResolverContext && $productConfig->isMapped()) {
            $product = $this->resolveEntityValue($resolverContext->getEntity(), $productConfig->getValue());
            if ($product) {
                $productBox->setProduct($product);
                $productBox->setProductId($product->getId());
            }
        }

        if ($productConfig->isStatic()) {
            $this->resolveProductFromRemote($slot, $productBox, $result, $productConfig->getValue());
        }
    }

    private function resolveProductFromRemote(CmsSlotEntity $slot, ProductBoxStruct $productBox, SlotDataResolveResult $result, string $productId): void
    {
        $searchResult = $result->get('product_' . $slot->getUniqueIdentifier());
        if (!$searchResult) {
            return;
        }

        /** @var SalesChannelProductEntity|null $product */
        $product = $searchResult->get($productId);
        if (!$product) {
            return;
        }

        $productBox->setProduct($product);
        $productBox->setProductId($product->getId());
    }
}
