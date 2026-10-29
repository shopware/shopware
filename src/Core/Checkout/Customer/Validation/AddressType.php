<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Validation;

use Shopware\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
enum AddressType: string
{
    case BILLING = 'billing';
    case SHIPPING = 'shipping';
}
