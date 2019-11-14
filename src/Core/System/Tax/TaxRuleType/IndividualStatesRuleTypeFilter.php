<?php declare(strict_types=1);

namespace Shopware\Core\System\Tax\TaxRuleType;

use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\System\Tax\Aggregate\TaxRule\TaxRuleEntity;

class IndividualStatesRuleTypeFilter implements TaxRuleTypeFilterInterface
{
    public const TECHNICAL_NAME = 'individual_states';

    public function match(TaxRuleEntity $taxRuleEntity, ?CustomerEntity $customer, ShippingLocation $shippingLocation): bool
    {
        if ($taxRuleEntity->getType()->getTechnicalName() !== self::TECHNICAL_NAME
            || !$this->metPreconditions($taxRuleEntity, $customer, $shippingLocation)
        ) {
            return false;
        }

        $stateId = $this->getStateId($customer, $shippingLocation);
        $states = $taxRuleEntity->getData()['states'];

        if (!in_array($stateId, $states, true)) {
            return false;
        }

        return true;
    }

    private function metPreconditions(TaxRuleEntity $taxRuleEntity, ?CustomerEntity $customer, ShippingLocation $shippingLocation): bool
    {
        if ($this->getStateId($customer, $shippingLocation) === null) {
            return false;
        }

        if ($customer !== null && $customer->getActiveBillingAddress()) {
            return $customer->getActiveBillingAddress()->getCountryId() === $taxRuleEntity->getCountryId();
        }

        return $shippingLocation->getCountry()->getId() === $taxRuleEntity->getCountryId();
    }

    private function getStateId(?CustomerEntity $customer, ShippingLocation $shippingLocation): ?string
    {
        if ($customer !== null && $customer->getActiveBillingAddress() !== null) {
            return $customer->getActiveBillingAddress()->getCountryStateId();
        }

        return $shippingLocation->getState() !== null ? $shippingLocation->getState()->getId() : null;
    }
}
