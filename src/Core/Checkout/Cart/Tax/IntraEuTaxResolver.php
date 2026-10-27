<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Tax;

use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerVatIdentification;
use Shopware\Core\Checkout\Customer\Validation\VatIdPatternProvider;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Country\CountryEntity;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('checkout')]
class IntraEuTaxResolver
{
    public function __construct(
        private readonly VatIdPatternProvider $vatIdPatternProvider,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /**
     * CartRuleLoader applies the cart amount and currency thresholds.
     *
     * @param array<string>|null $vatIds
     */
    public function isExempt(?string $accountType, ?array $vatIds, CountryEntity $deliveryCountry, string $salesChannelId): bool
    {
        if (!$this->appliesTo($accountType, $deliveryCountry)) {
            return false;
        }

        if (!$deliveryCountry->getIsEu()) {
            return true;
        }

        return $this->hasIntraCommunityVatId($vatIds, $deliveryCountry, $salesChannelId);
    }

    /**
     * @param array<string>|null $vatIds
     */
    public function getTaxCountryId(?string $accountType, ?array $vatIds, CountryEntity $deliveryCountry, string $salesChannelId): string
    {
        if (!$this->appliesTo($accountType, $deliveryCountry)
            || !$deliveryCountry->getIsEu()
            || !$this->vatIdPatternProvider->isSellerCountryVatId($vatIds, $salesChannelId)
        ) {
            return $deliveryCountry->getId();
        }

        return $this->vatIdPatternProvider->getSellerCountryId($salesChannelId);
    }

    public function isIntraCommunityDelivery(OrderEntity $order): bool
    {
        $accountType = $order->getOrderCustomer()?->getCustomer()?->getAccountType();
        if ($accountType !== CustomerEntity::ACCOUNT_TYPE_BUSINESS) {
            return false;
        }

        $delivery = Feature::isActive('v6.8.0.0')
            ? $order->getPrimaryOrderDelivery()
            : $order->getDeliveries()?->first();
        $country = $delivery?->getShippingOrderAddress()?->getCountry();

        if ($country === null || !$country->getIsEu() || $order->getPrice()->getTaxStatus() !== CartPrice::TAX_STATE_FREE) {
            return false;
        }

        $vatIds = $order->getOrderCustomer()?->getVatIds();
        if (!$this->hasIntraCommunityVatId($vatIds, $country, $order->getSalesChannelId())) {
            return false;
        }

        if (!$country->getCheckVatIdPattern()) {
            return true;
        }

        return $this->validator->validate($vatIds, [
            new NotBlank(),
            new CustomerVatIdentification(countryId: $country->getId(), salesChannelId: $order->getSalesChannelId()),
        ])->count() === 0;
    }

    /**
     * @param array<string>|null $vatIds
     */
    private function hasIntraCommunityVatId(?array $vatIds, CountryEntity $deliveryCountry, string $salesChannelId): bool
    {
        $vatIds = array_filter($vatIds ?? []);
        if ($vatIds === []
            || $this->vatIdPatternProvider->isDomesticSupply($deliveryCountry->getIso(), $salesChannelId)
            || $this->vatIdPatternProvider->isSellerCountryVatId($vatIds, $salesChannelId)
        ) {
            return false;
        }

        $vatPattern = $deliveryCountry->getVatIdPattern();
        if ($vatPattern !== null && $vatPattern !== '' && $deliveryCountry->getCheckVatIdPattern()) {
            foreach ($vatIds as $vatId) {
                if (!$this->vatIdPatternProvider->acceptsVatId($vatId, $vatPattern, $deliveryCountry->getIsEu(), $salesChannelId)) {
                    return false;
                }
            }
        }

        return true;
    }

    private function appliesTo(?string $accountType, CountryEntity $deliveryCountry): bool
    {
        return $accountType === CustomerEntity::ACCOUNT_TYPE_BUSINESS && $deliveryCountry->getCompanyTax()->getEnabled();
    }
}
