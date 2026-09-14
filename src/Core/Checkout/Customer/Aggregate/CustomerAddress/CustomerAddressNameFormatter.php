<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
final class CustomerAddressNameFormatter
{
    private function __construct()
    {
    }

    public static function displayName(CustomerAddressEntity $address): string
    {
        $personName = trim($address->getFirstName() . ' ' . $address->getLastName());

        return $personName !== '' ? $personName : trim($address->getCompany() ?? '');
    }
}
