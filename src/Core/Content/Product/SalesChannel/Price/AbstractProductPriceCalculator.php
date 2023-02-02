<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Price;

use Shopware\Core\System\SalesChannel\SalesChannelContext;

abstract class AbstractProductPriceCalculator
{
    abstract public function getDecorated(): AbstractProductPriceCalculator;

    abstract public function calculate(iterable $products, SalesChannelContext $context): void;
}
