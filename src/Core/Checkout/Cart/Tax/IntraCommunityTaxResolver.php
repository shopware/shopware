<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Tax;

use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Validation\VatIdPatternProvider;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Decides the intra-Eu B2B exemption and the tax country by the seller's member state. The seller's
 * country is set in Settings > Basic information and falls back to the sales channel's default country.
 *
 * @final
 */
#[Package('checkout')]
class IntraCommunityTaxResolver implements ResetInterface
{
    private const SELLER_COUNTRY_CONFIG_KEY = 'core.basicInformation.sellerCountryId';

    /**
     * @var array<string, string> sales channel id => id of its default country
     */
    private array $salesChannelCountryIds = [];

    /**
     * @internal
     *
     * @param EntityRepository<CountryCollection> $countryRepository
     */
    public function __construct(
        private readonly VatIdPatternProvider $vatIdPatternProvider,
        private readonly SystemConfigService $systemConfigService,
        private readonly EntityRepository $countryRepository,
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
        $vatIds = array_filter($vatIds ?? []);

        // Keep VAT-based country selection opt-in through the destination's B2B setting.
        if (!$this->appliesTo($accountType, $deliveryCountry)
            || !$deliveryCountry->getIsEu()
            || $vatIds === []
            || !$this->hasSellerStateVatId($vatIds, $this->getSellerState($salesChannelId))
        ) {
            return $deliveryCountry->getId();
        }

        return $this->getSellerCountryId($salesChannelId);
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

        return $this->hasIntraCommunityVatId($order->getOrderCustomer()?->getVatIds(), $country, $order->getSalesChannelId());
    }

    public function reset(): void
    {
        $this->salesChannelCountryIds = [];
    }

    /**
     * @param array<string>|null $vatIds
     */
    private function hasIntraCommunityVatId(?array $vatIds, CountryEntity $deliveryCountry, string $salesChannelId): bool
    {
        $vatIds = array_filter($vatIds ?? []);
        if ($vatIds === []) {
            return false;
        }

        $sellerState = $this->getSellerState($salesChannelId);
        if (($sellerState !== null && $deliveryCountry->getIso() === $sellerState) || $this->hasSellerStateVatId($vatIds, $sellerState)) {
            return false;
        }

        $pattern = (string) $deliveryCountry->getVatIdPattern();
        if ($pattern === '' || !$deliveryCountry->getCheckVatIdPattern()) {
            return true;
        }

        foreach ($vatIds as $vatId) {
            // Without a seller state, a VAT ID of another member state cannot be told apart from a domestic one
            if (!$this->vatIdPatternProvider->matches($pattern, $vatId)
                && ($sellerState === null || $this->vatIdPatternProvider->getMemberState($vatId) === null)
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string> $vatIds
     */
    private function hasSellerStateVatId(array $vatIds, ?string $sellerState): bool
    {
        if ($sellerState === null) {
            return false;
        }

        foreach ($vatIds as $vatId) {
            if ($this->vatIdPatternProvider->getMemberState($vatId) === $sellerState) {
                return true;
            }
        }

        return false;
    }

    private function appliesTo(?string $accountType, CountryEntity $deliveryCountry): bool
    {
        return $accountType === CustomerEntity::ACCOUNT_TYPE_BUSINESS && $deliveryCountry->getCompanyTax()->getEnabled();
    }

    /**
     * @return string|null the ISO code of the seller's member state, null when the seller's country lies outside the EU
     */
    private function getSellerState(string $salesChannelId): ?string
    {
        return $this->vatIdPatternProvider->getMemberStateOfCountry($this->getSellerCountryId($salesChannelId));
    }

    private function getSellerCountryId(string $salesChannelId): string
    {
        // Reading the config of an empty sales channel throws
        if ($salesChannelId === '') {
            return '';
        }

        $countryId = $this->systemConfigService->getString(self::SELLER_COUNTRY_CONFIG_KEY, $salesChannelId);

        return strtolower($countryId !== '' ? $countryId : $this->getSalesChannelCountryId($salesChannelId));
    }

    private function getSalesChannelCountryId(string $salesChannelId): string
    {
        if (\array_key_exists($salesChannelId, $this->salesChannelCountryIds)) {
            return $this->salesChannelCountryIds[$salesChannelId];
        }

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('salesChannelDefaultAssignments.id', $salesChannelId));
        $criteria->setTitle('intra-eu-tax-resolver::sales-channel-country');

        $countryId = $this->countryRepository->searchIds($criteria, Context::createDefaultContext())->firstId();

        return $this->salesChannelCountryIds[$salesChannelId] = (string) $countryId;
    }
}
