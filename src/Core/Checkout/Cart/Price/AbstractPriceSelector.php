<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Price;

use Shopware\Core\Checkout\Cart\Price\Struct\SelectedPrice;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
abstract class AbstractPriceSelector
{
    abstract public function getDecorated(): AbstractPriceSelector;

    abstract public function select(Price $price, SalesChannelContext $context): SelectedPrice;
}
