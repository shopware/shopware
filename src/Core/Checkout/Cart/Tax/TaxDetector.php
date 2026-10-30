<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Tax;

use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
class TaxDetector extends AbstractTaxDetector
{
    public function getDecorated(): AbstractTaxDetector
    {
        throw new DecorationPatternException(self::class);
    }

    public function useGross(SalesChannelContext $context): bool
    {
        return $context->getCurrentCustomerGroup()->getDisplayGross();
    }

    public function isNetDelivery(SalesChannelContext $context): bool
    {
        $shippingLocationCountry = $context->getShippingLocation()->getCountry();

        if ($context->getCustomer()?->getAccountType() === CustomerEntity::ACCOUNT_TYPE_BUSINESS) {
            return $this->isCompanyTaxFree($context, $shippingLocationCountry);
        }

        return $shippingLocationCountry->getCustomerTax()->getEnabled();
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

        if (!$shippingLocationCountry->getIsEu()) {
            return true;
        }

        $vatPattern = $shippingLocationCountry->getVatIdPattern();
        $vatIds = array_filter($customer->getVatIds() ?? []);

        if ($vatIds === []) {
            return false;
        }

        if ($vatPattern !== null && $vatPattern !== '' && $shippingLocationCountry->getCheckVatIdPattern()) {
            $regex = '/^(?:' . $vatPattern . ')$/D';

            foreach ($vatIds as $vatId) {
                if (!preg_match($regex, $vatId)) {
                    return false;
                }
            }
        }

        return true;
    }
}
