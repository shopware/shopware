<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderCustomer;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
final class OrderCustomerNameFormatter
{
    private function __construct()
    {
    }

    public static function buyerName(?OrderCustomerEntity $customer): string
    {
        if ($customer === null) {
            return '';
        }

        $personName = trim($customer->getFirstName() . ' ' . $customer->getLastName());
        $company = trim($customer->getCompany() ?? '');

        return match (true) {
            $company === '' => $personName,
            $personName === '' || $personName === $company => $company,
            default => $personName . ' - ' . $company,
        };
    }
}
