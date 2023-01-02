<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Price;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('inventory')]
abstract class AbstractProductPriceCalculator
{
    abstract public function getDecorated(): AbstractProductPriceCalculator;

    abstract public function calculate(iterable $products, SalesChannelContext $context): void;
}
