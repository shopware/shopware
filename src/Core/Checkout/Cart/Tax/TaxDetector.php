<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Tax;

use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Framework\Feature;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class TaxDetector
{
    public function useGross(SalesChannelContext $context): bool
    {
        return $context->getCurrentCustomerGroup()->getDisplayGross();
    }

    public function isNetDelivery(SalesChannelContext $context): bool
    {
        $shippingLocationCountry = $context->getShippingLocation()->getCountry();
        $countryTaxFree = Feature::isActive('FEATURE_NEXT_14114')
            ? $shippingLocationCountry->getCustomerTax()->getEnabled()
            : $shippingLocationCountry->getTaxFree();

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

        $countryCompanyTaxFree = Feature::isActive('FEATURE_NEXT_14114')
            ? $shippingLocationCountry->getCompanyTax()->getEnabled()
            : $shippingLocationCountry->getCompanyTaxFree();

        if (!$countryCompanyTaxFree || !$customer || !$customer->getCompany()) {
            return false;
        }

        $vatPattern = $shippingLocationCountry->getVatIdPattern();
        $vatIds = $customer->getVatIds();

        if ($vatPattern === null || empty($vatIds)) {
            return false;
        }

        $regex = '/^' . $vatPattern . '$/i';

        foreach ($vatIds as $vatId) {
            if (!preg_match($regex, $vatId)) {
                return false;
            }
        }

        return true;
    }
}
