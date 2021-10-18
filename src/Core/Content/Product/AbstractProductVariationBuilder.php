<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product;

abstract class AbstractProductVariationBuilder
{
    abstract public function getDecorated(): AbstractProductVariationBuilder;

    abstract public function build(ProductEntity $product): void;
}
