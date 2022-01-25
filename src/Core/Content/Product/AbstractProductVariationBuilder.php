<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\Feature;

if (Feature::isActive('v6_5_0_0')) {
    abstract class AbstractProductVariationBuilder
    {
        abstract public function getDecorated(): AbstractProductVariationBuilder;

        abstract public function build(Entity $product): void;
    }
} else {
    abstract class AbstractProductVariationBuilder
    {
        abstract public function getDecorated(): AbstractProductVariationBuilder;

        abstract public function build(ProductEntity $product): void;
    }
}
