<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Cms;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\SalesChannel\Struct\BuyBoxStruct;
use Shopware\Core\Content\Product\SalesChannel\Detail\ProductConfiguratorLoader;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;

/**
 * @internal (flag:FEATURE_NEXT_10078)
 */
class BuyBoxCmsElementResolver extends AbstractProductDetailCmsElementResolver
{
    /**
     * @var ProductConfiguratorLoader
     */
    private $configuratorLoader;

    public function __construct(ProductConfiguratorLoader $configuratorLoader)
    {
        $this->configuratorLoader = $configuratorLoader;
    }

    public function getType(): string
    {
        return 'buy-box';
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $buyBox = new BuyBoxStruct();
        $slot->setData($buyBox);

        $config = $slot->getFieldConfig();
        $productConfig = $config->get('product');

        if ($productConfig === null) {
            return;
        }

        $product = null;

        if ($productConfig->isMapped() && $resolverContext instanceof EntityResolverContext) {
            $product = $this->resolveEntityValue($resolverContext->getEntity(), $productConfig->getValue());
        }

        if ($productConfig->isStatic()) {
            $product = $this->getSlotProduct($slot, $result, $productConfig->getValue());
        }

        /** @var SalesChannelProductEntity|null $product */
        if ($product !== null) {
            $buyBox->setProduct($product);
            $buyBox->setProductId($product->getId());
            $buyBox->setConfiguratorSettings($this->configuratorLoader->load($product, $resolverContext->getSalesChannelContext()));
        }
    }
}
