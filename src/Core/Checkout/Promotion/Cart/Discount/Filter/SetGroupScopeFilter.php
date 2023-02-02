<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart\Discount\Filter;

use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountLineItem;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackageCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

abstract class SetGroupScopeFilter
{
    abstract public function getDecorated(): SetGroupScopeFilter;

    abstract public function filter(DiscountLineItem $discount, DiscountPackageCollection $packages, SalesChannelContext $context): DiscountPackageCollection;
}
