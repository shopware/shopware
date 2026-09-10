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

    /**
     * The name to address the buyer by, for a greeting or a recipient header. Unlike buyerName() it
     * never joins the two, because a person and their company read as one name there.
     */
    public static function displayName(?OrderCustomerEntity $customer): string
    {
        if ($customer === null) {
            return '';
        }

        $personName = trim($customer->getFirstName() . ' ' . $customer->getLastName());

        return $personName !== '' ? $personName : trim($customer->getCompany() ?? '');
    }

    /**
     * The buyer block of a document, where the company belongs next to the contact person.
     */
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
