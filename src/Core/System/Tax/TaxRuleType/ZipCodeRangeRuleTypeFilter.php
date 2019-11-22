<?php declare(strict_types=1);

namespace Shopware\Core\System\Tax\TaxRuleType;

use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\System\Tax\Aggregate\TaxRule\TaxRuleEntity;

class ZipCodeRangeRuleTypeFilter implements TaxRuleTypeFilterInterface
{
    public const TECHNICAL_NAME = 'zip_code_range';

    public function match(TaxRuleEntity $taxRuleEntity, ?CustomerEntity $customer, ShippingLocation $shippingLocation): bool
    {
        if ($taxRuleEntity->getType()->getTechnicalName() !== self::TECHNICAL_NAME
            || !$this->metPreconditions($taxRuleEntity, $customer, $shippingLocation)
        ) {
            return false;
        }

        $zipCode = $this->getZipCode($customer, $shippingLocation);

        $toZipCode = $taxRuleEntity->getData()['toZipCode'];
        $fromZipCode = $taxRuleEntity->getData()['fromZipCode'];

        if ($fromZipCode === null || $toZipCode === null || $zipCode < $fromZipCode || $zipCode > $toZipCode) {
            return false;
        }

        return true;
    }

    private function metPreconditions(TaxRuleEntity $taxRuleEntity, ?CustomerEntity $customer, ShippingLocation $shippingLocation): bool
    {
        if ($this->getZipCode($customer, $shippingLocation) === null) {
            return false;
        }

        if ($customer !== null && $customer->getActiveBillingAddress()) {
            return $customer->getActiveBillingAddress()->getCountryId() === $taxRuleEntity->getCountryId();
        }

        return $shippingLocation->getCountry()->getId() === $taxRuleEntity->getCountryId();
    }

    private function getZipCode(?CustomerEntity $customer, ShippingLocation $shippingLocation): ?string
    {
        if ($customer !== null && $customer->getActiveBillingAddress() !== null) {
            return $customer->getActiveBillingAddress()->getZipcode();
        }

        $address = $shippingLocation->getAddress();

        return $address !== null ? $address->getZipcode() : null;
    }
}
