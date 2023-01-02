<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart\Discount\Filter;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackageCollection;

/**
 * @package checkout
 */
#[Package('checkout')]
interface FilterSorterInterface
{
    public function getKey(): string;

    public function sort(DiscountPackageCollection $packages): DiscountPackageCollection;
}
