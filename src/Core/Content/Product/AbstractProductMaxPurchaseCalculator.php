<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product;

use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

abstract class AbstractProductMaxPurchaseCalculator
{
    abstract public function getDecorated(): AbstractProductMaxPurchaseCalculator;

    abstract public function calculate(SalesChannelProductEntity $product, SalesChannelContext $context): int;
}
