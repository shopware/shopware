<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Tax;

use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
class TaxDetector
{
    public function useGross(SalesChannelContext $context): bool
    {
        return $context->getCurrentCustomerGroup()->getDisplayGross();
    }

    public function isNetDelivery(SalesChannelContext $context): bool
    {
        $shippingLocationCountry = $context->getShippingLocation()->getCountry();
        $countryTaxFree = $shippingLocationCountry->getCustomerTax()->getEnabled();

        if ($countryTaxFree) {
            return true;
        }

        return $this->isCompanyTaxFree($context, $shippingLocationCountry);
    }

    public function getTaxState(SalesChannelContext $context): string
    {
        if ($this->isNetDelivery($context)) {
            return CartPrice::TAX_STATE_FREE;
        }

        if ($this->useGross($context)) {
            return CartPrice::TAX_STATE_GROSS;
        }

        return CartPrice::TAX_STATE_NET;
    }

    public function isCompanyTaxFree(SalesChannelContext $context, CountryEntity $shippingLocationCountry): bool
    {
        $customer = $context->getCustomer();

        $countryCompanyTaxFree = $shippingLocationCountry->getCompanyTax()->getEnabled();

        if (!$countryCompanyTaxFree || !$customer || !$customer->getCompany()) {
            return false;
        }

        $vatPattern = $shippingLocationCountry->getVatIdPattern();
        $vatIds = array_filter($customer->getVatIds() ?? []);

        if (empty($vatIds)) {
            return false;
        }

        if (!empty($vatPattern) && $shippingLocationCountry->getCheckVatIdPattern()) {
            $regex = '/^' . $vatPattern . '$/i';

            foreach ($vatIds as $vatId) {
                if (!preg_match($regex, $vatId)) {
                    return false;
                }
            }
        }

        return true;
    }
}
