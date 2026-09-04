<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Tax;

use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Validation\VatIdPatternProvider;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
class TaxDetector extends AbstractTaxDetector
{
    /**
     * @internal
     */
    public function __construct(private readonly VatIdPatternProvider $vatIdPatternProvider)
    {
    }

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

        if (!$countryCompanyTaxFree || !$customer || $customer->getAccountType() !== CustomerEntity::ACCOUNT_TYPE_BUSINESS) {
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
            foreach ($vatIds as $vatId) {
                // An intra-EU B2B supply is tax free because the customer holds a VAT ID of another
                // member state, not because the delivery goes to that state.
                if (!$this->vatIdPatternProvider->acceptsVatId($vatId, $vatPattern, true, $context->getSalesChannelId())) {
                    return false;
                }
            }
        }

        return true;
    }
}
