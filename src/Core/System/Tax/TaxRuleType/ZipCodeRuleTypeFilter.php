<?php declare(strict_types=1);

namespace Shopware\Core\System\Tax\TaxRuleType;

use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Tax\Aggregate\TaxRule\TaxRuleEntity;

#[Package('customer-order')]
class ZipCodeRuleTypeFilter implements TaxRuleTypeFilterInterface
{
    final public const TECHNICAL_NAME = 'zip_code';

    public function match(TaxRuleEntity $taxRuleEntity, ?CustomerEntity $customer, ShippingLocation $shippingLocation): bool
    {
        if ($taxRuleEntity->getType()->getTechnicalName() !== self::TECHNICAL_NAME
            || !$this->metPreconditions($taxRuleEntity, $shippingLocation)
        ) {
            return false;
        }

        $shippingZipCode = $this->getZipCode($shippingLocation);

        $zipCode = $taxRuleEntity->getData()['zipCode'];

        if ($shippingZipCode !== $zipCode) {
            return false;
        }

        return true;
    }

    private function metPreconditions(TaxRuleEntity $taxRuleEntity, ShippingLocation $shippingLocation): bool
    {
        if ($this->getZipCode($shippingLocation) === null) {
            return false;
        }

        return $shippingLocation->getCountry()->getId() === $taxRuleEntity->getCountryId();
    }

    private function getZipCode(ShippingLocation $shippingLocation): ?string
    {
        return $shippingLocation->getAddress() !== null ? $shippingLocation->getAddress()->getZipcode() : null;
    }
}
