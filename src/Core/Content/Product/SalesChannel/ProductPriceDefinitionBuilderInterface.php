<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel;

use Shopware\Core\Checkout\Cart\Price\Struct\PriceDefinitionCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface ProductPriceDefinitionBuilderInterface
{
    public function buildPriceDefinitions(ProductEntity $product, SalesChannelContext $context): PriceDefinitionCollection;

    public function buildPriceDefinition(ProductEntity $product, SalesChannelContext $context): QuantityPriceDefinition;

    public function buildListingPriceDefinition(ProductEntity $product, SalesChannelContext $context): QuantityPriceDefinition;

    public function buildPriceDefinitionForQuantity(ProductEntity $product, SalesChannelContext $context, int $quantity): QuantityPriceDefinition;
}
