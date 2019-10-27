<?php declare(strict_types=1);

namespace Shopware\Core\System\Tax\TaxAreaRuleType;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Tax\Aggregate\TaxAreaRule\TaxAreaRuleEntity;

class IndividualStatesRuleTypeFilter implements TaxAreaRuleTypeFilterInterface
{
    public const TECHNICAL_NAME = 'individual_states';

    public function getTaxRate(TaxAreaRuleEntity $taxAreaRuleEntity, SalesChannelContext $salesChannelContext): float
    {
        if ($taxAreaRuleEntity->getTaxAreaRuleType()->getTechnicalName() !== self::TECHNICAL_NAME
            || !$this->metPreconditions($taxAreaRuleEntity, $salesChannelContext)
        ) {
            throw new NotMatchingTaxAreaRule(self::TECHNICAL_NAME);
        }

        $stateId = $this->getStateId($salesChannelContext);
        $states = $taxAreaRuleEntity->getData()['states'];

        if (!in_array($stateId, $states, true)) {
            throw new NotMatchingTaxAreaRule(self::TECHNICAL_NAME);
        }

        return $taxAreaRuleEntity->getTaxRate();
    }

    private function metPreconditions(TaxAreaRuleEntity $taxAreaRuleEntity, SalesChannelContext $salesChannelContext): bool
    {
        if ($this->getStateId($salesChannelContext) === null) {
            return false;
        }

        if (($customer = $salesChannelContext->getCustomer()) && $customer->getActiveBillingAddress()) {
            return $customer->getActiveBillingAddress()->getCountryId() === $taxAreaRuleEntity->getCountryId();
        }

        return $salesChannelContext->getShippingLocation()->getCountry()->getId() === $taxAreaRuleEntity->getCountryId();
    }

    private function getStateId(SalesChannelContext $salesChannelContext): ?string
    {
        if (($customer = $salesChannelContext->getCustomer()) && $customer->getActiveBillingAddress() !== null) {
            return $customer->getActiveBillingAddress()->getCountryStateId();
        }

        return $salesChannelContext->getShippingLocation()->getState() !== null ? $salesChannelContext->getShippingLocation()->getState()->getId() : null;
    }
}
