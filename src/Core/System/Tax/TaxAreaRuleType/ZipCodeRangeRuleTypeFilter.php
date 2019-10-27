<?php declare(strict_types=1);

namespace Shopware\Core\System\Tax\TaxAreaRuleType;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Tax\Aggregate\TaxAreaRule\TaxAreaRuleEntity;

class ZipCodeRangeRuleTypeFilter implements TaxAreaRuleTypeFilterInterface
{
    public const TECHNICAL_NAME = 'zip_code_range';

    public function getTaxRate(TaxAreaRuleEntity $taxAreaRuleEntity, SalesChannelContext $salesChannelContext): float
    {
        if ($taxAreaRuleEntity->getTaxAreaRuleType()->getTechnicalName() !== self::TECHNICAL_NAME
            || !$this->metPreconditions($taxAreaRuleEntity, $salesChannelContext)
        ) {
            throw new NotMatchingTaxAreaRule(self::TECHNICAL_NAME);
        }

        $zipCode = $this->getZipCode($salesChannelContext);

        $toZipCode = $taxAreaRuleEntity->getData()['toZipCode'];
        $fromZipCode = $taxAreaRuleEntity->getData()['fromZipCode'];

        if ($fromZipCode === null || $toZipCode === null || $zipCode < $fromZipCode || $zipCode > $toZipCode) {
            throw new NotMatchingTaxAreaRule(self::TECHNICAL_NAME);
        }

        return $taxAreaRuleEntity->getTaxRate();
    }

    private function metPreconditions(TaxAreaRuleEntity $taxAreaRuleEntity, SalesChannelContext $salesChannelContext): bool
    {
        if ($this->getZipCode($salesChannelContext) === null) {
            return false;
        }

        if (($customer = $salesChannelContext->getCustomer()) && $customer->getActiveBillingAddress()) {
            return $customer->getActiveBillingAddress()->getCountryId() === $taxAreaRuleEntity->getCountryId();
        }

        return $salesChannelContext->getShippingLocation()->getCountry()->getId() === $taxAreaRuleEntity->getCountryId();
    }

    private function getZipCode(SalesChannelContext $salesChannelContext): ?string
    {
        if (($customer = $salesChannelContext->getCustomer()) && $customer->getActiveBillingAddress() !== null) {
            return $customer->getActiveBillingAddress()->getZipcode();
        }

        $address = $salesChannelContext->getShippingLocation()->getAddress();

        return $address !== null ? $address->getZipcode() : null;
    }
}
