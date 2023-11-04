<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart\Discount\Filter;

use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountLineItem;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackageCollection;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
abstract class PackageFilter
{
    abstract public function getDecorated(): PackageFilter;

    abstract public function filterPackages(DiscountLineItem $discount, DiscountPackageCollection $packages, int $originalPackageCount): DiscountPackageCollection;
}
