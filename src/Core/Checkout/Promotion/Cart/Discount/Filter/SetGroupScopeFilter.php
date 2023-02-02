<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart\Discount\Filter;

use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountLineItem;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackageCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
abstract class SetGroupScopeFilter
{
    abstract public function getDecorated(): SetGroupScopeFilter;

    abstract public function filter(DiscountLineItem $discount, DiscountPackageCollection $packages, SalesChannelContext $context): DiscountPackageCollection;
}
