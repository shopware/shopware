<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Gateway\Command;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;

/**
 * @template TElement of AbstractCheckoutGatewayCommand = AbstractCheckoutGatewayCommand
 *
 * @extends Collection<TElement>
 */
#[Package('checkout')]
class CheckoutGatewayCommandCollection extends Collection
{
}
