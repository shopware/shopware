<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Cms;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ProductBoxStruct;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class ProductBoxCmsElementResolver extends AbstractCmsElementResolver
{
    private SystemConfigService $systemConfigService;

    public function __construct(SystemConfigService $systemConfigService)
    {
        $this->systemConfigService = $systemConfigService;
    }

    public function getType(): string
    {
        return 'product-box';
    }

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        $productConfig = $slot->getFieldConfig()->get('product');
        if ($productConfig === null || $productConfig->isMapped() || $productConfig->getValue() === null) {
            return null;
        }

        $criteria = new Criteria([$productConfig->getStringValue()]);

        $criteriaCollection = new CriteriaCollection();
        $criteriaCollection->add('product_' . $slot->getUniqueIdentifier(), ProductDefinition::class, $criteria);

        return $criteriaCollection;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $productBox = new ProductBoxStruct();
        $slot->setData($productBox);

        $productConfig = $slot->getFieldConfig()->get('product');
        if ($productConfig === null || $productConfig->getValue() === null) {
            return;
        }

        if ($resolverContext instanceof EntityResolverContext && $productConfig->isMapped()) {
            /** @var SalesChannelProductEntity $product */
            $product = $this->resolveEntityValue($resolverContext->getEntity(), $productConfig->getStringValue());

            $productBox->setProduct($product);
            $productBox->setProductId($product->getId());
        }

        if ($productConfig->isStatic()) {
            $this->resolveProductFromRemote($slot, $productBox, $result, $productConfig->getStringValue(), $resolverContext->getSalesChannelContext());
        }
    }

    private function resolveProductFromRemote(
        CmsSlotEntity $slot,
        ProductBoxStruct $productBox,
        ElementDataCollection $result,
        string $productId,
        SalesChannelContext $salesChannelContext
    ): void {
        $searchResult = $result->get('product_' . $slot->getUniqueIdentifier());
        if ($searchResult === null) {
            return;
        }

        /** @var SalesChannelProductEntity|null $product */
        $product = $searchResult->get($productId);
        if ($product === null) {
            return;
        }

        if ($this->systemConfigService->get('core.listing.hideCloseoutProductsWhenOutOfStock', $salesChannelContext->getSalesChannel()->getId())
            && $product->getIsCloseout()
            && $product->getAvailableStock() <= 0
        ) {
            return;
        }

        $productBox->setProduct($product);
        $productBox->setProductId($product->getId());
    }
}
