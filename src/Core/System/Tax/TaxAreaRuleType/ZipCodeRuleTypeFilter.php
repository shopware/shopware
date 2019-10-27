<?php declare(strict_types=1);

namespace Shopware\Core\System\Tax\TaxAreaRuleType;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Tax\Aggregate\TaxAreaRule\TaxAreaRuleEntity;

class ZipCodeRuleTypeFilter implements TaxAreaRuleTypeFilterInterface
{
    public const TECHNICAL_NAME = 'zip_code';

    public function getTaxRate(TaxAreaRuleEntity $taxAreaRuleEntity, SalesChannelContext $salesChannelContext): float
    {
        if ($taxAreaRuleEntity->getTaxAreaRuleType()->getTechnicalName() !== self::TECHNICAL_NAME
            || !$this->metPreconditions($taxAreaRuleEntity, $salesChannelContext)) {
            throw new NotMatchingTaxAreaRule(self::TECHNICAL_NAME);
        }

        $shippingZipCode = $this->getZipCode($salesChannelContext);

        $zipCode = $taxAreaRuleEntity->getData()['zipCode'];

        if ($shippingZipCode !== $zipCode) {
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
