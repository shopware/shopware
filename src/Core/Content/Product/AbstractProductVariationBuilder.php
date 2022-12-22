<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\Feature;

if (Feature::isActive('v6.5.0.0')) {
    /**
     * @package inventory
     */
    abstract class AbstractProductVariationBuilder
    {
        abstract public function getDecorated(): AbstractProductVariationBuilder;

        abstract public function build(Entity $product): void;
    }
} else {
    /**
     * @package inventory
     */
    abstract class AbstractProductVariationBuilder
    {
        abstract public function getDecorated(): AbstractProductVariationBuilder;

        abstract public function build(ProductEntity $product): void;
    }
}
