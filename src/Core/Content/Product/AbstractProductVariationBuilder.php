<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product;

use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;

abstract class AbstractProductVariationBuilder
{
    abstract public function getDecorated(): AbstractProductVariationBuilder;

    abstract public function build(SalesChannelProductEntity $product): void;
}
