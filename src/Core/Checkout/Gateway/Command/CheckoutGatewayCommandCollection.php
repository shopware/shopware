<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Gateway\Command;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;

/**
 * @extends Collection<AbstractCheckoutGatewayCommand>
 */
#[Package('checkout')]
class CheckoutGatewayCommandCollection extends Collection
{
}
