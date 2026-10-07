<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Detail;

use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Adapts minimal product data to the existing ProductConfiguratorLoader API without changing its public contract.
 *
 * @internal
 */
#[Package('inventory')]
final class PartialProductConfiguratorLoader
{
    public function __construct(
        private readonly ProductConfiguratorLoader $productConfiguratorLoader,
    ) {
    }

    public function load(
        ProductConfiguratorProductData $productData,
        SalesChannelContext $context,
    ): PropertyGroupCollection {
        $product = new SalesChannelProductEntity();
        $product->setId($productData->id);
        $product->setParentId($productData->parentId);
        $product->setOptionIds($productData->optionIds);
        $product->setVariantListingConfig($productData->variantListingConfig);

        return $this->productConfiguratorLoader->load($product, $context);
    }
}
