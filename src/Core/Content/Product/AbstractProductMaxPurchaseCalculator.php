<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product;

use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\Feature;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

if (Feature::isActive('v6.5.0.0')) {
    /**
     * @package inventory
     */
    abstract class AbstractProductMaxPurchaseCalculator
    {
        abstract public function getDecorated(): AbstractProductMaxPurchaseCalculator;

        abstract public function calculate(Entity $product, SalesChannelContext $context): int;
    }
} else {
    /**
     * @package inventory
     */
    abstract class AbstractProductMaxPurchaseCalculator
    {
        abstract public function getDecorated(): AbstractProductMaxPurchaseCalculator;

        abstract public function calculate(SalesChannelProductEntity $product, SalesChannelContext $context): int;
    }
}
