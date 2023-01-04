<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

if (Feature::isActive('v6.5.0.0')) {
    #[Package('inventory')]
    abstract class AbstractProductVariationBuilder
    {
        abstract public function getDecorated(): AbstractProductVariationBuilder;

        abstract public function build(Entity $product): void;
    }
} else {
    #[Package('inventory')]
    abstract class AbstractProductVariationBuilder
    {
        abstract public function getDecorated(): AbstractProductVariationBuilder;

        abstract public function build(ProductEntity $product): void;
    }
}
