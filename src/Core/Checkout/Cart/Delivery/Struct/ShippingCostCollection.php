<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Delivery\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;

/**
 * @internal
 *
 * @extends Collection<ShippingCost>
 */
#[Package('checkout')]
class ShippingCostCollection extends Collection
{
    protected function getExpectedClass(): ?string
    {
        return ShippingCost::class;
    }
}
