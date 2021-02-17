<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Price;

use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @feature-deprecated (flag:FEATURE_NEXT_10553) tag:v6.4.0 - Use \Shopware\Core\Content\Product\SalesChannel\Price\ProductPriceCalculatorInterface instead
 */
interface ProductPriceDefinitionBuilderInterface
{
    public function build(ProductEntity $product, SalesChannelContext $context, int $quantity = 1): ProductPriceDefinitions;
}
