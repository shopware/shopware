<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Price;

use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface ProductPriceDefinitionBuilderInterface
{
    public function build(ProductEntity $product, SalesChannelContext $context, int $quantity = 1): ProductPriceDefinitions;
}
