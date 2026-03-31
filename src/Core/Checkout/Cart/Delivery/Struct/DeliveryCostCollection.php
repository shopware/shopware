<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Delivery\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;

/**
 * @internal
 *
 * @extends Collection<DeliveryCost>
 */
#[Package('checkout')]
class DeliveryCostCollection extends Collection
{
    protected function getExpectedClass(): ?string
    {
        return DeliveryCost::class;
    }
}
