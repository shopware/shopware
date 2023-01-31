<?php declare(strict_types=1);

namespace Shopware\Core\System\Tax\TaxRuleType;

use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Tax\Aggregate\TaxRule\TaxRuleEntity;

#[Package('customer-order')]
class IndividualStatesRuleTypeFilter implements TaxRuleTypeFilterInterface
{
    final public const TECHNICAL_NAME = 'individual_states';

    public function match(TaxRuleEntity $taxRuleEntity, ?CustomerEntity $customer, ShippingLocation $shippingLocation): bool
    {
        if ($taxRuleEntity->getType()->getTechnicalName() !== self::TECHNICAL_NAME
            || !$this->metPreconditions($taxRuleEntity, $shippingLocation)
        ) {
            return false;
        }

        $stateId = $this->getStateId($shippingLocation);
        $states = $taxRuleEntity->getData()['states'];

        if (!\in_array($stateId, $states, true)) {
            return false;
        }

        return true;
    }

    private function metPreconditions(TaxRuleEntity $taxRuleEntity, ShippingLocation $shippingLocation): bool
    {
        if ($this->getStateId($shippingLocation) === null) {
            return false;
        }

        return $shippingLocation->getCountry()->getId() === $taxRuleEntity->getCountryId();
    }

    private function getStateId(ShippingLocation $shippingLocation): ?string
    {
        return $shippingLocation->getState() !== null ? $shippingLocation->getState()->getId() : null;
    }
}
