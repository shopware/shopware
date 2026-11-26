<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Tax;

use Shopware\Core\Checkout\Cart\Extension\ResolveDispatchCountryExtension;
use Shopware\Core\Checkout\Cart\Extension\ResolveVatIdsExtension;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Validation\VatIdPatternProvider;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
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
        private readonly ExtensionDispatcher $extensions,
    ) {
    }

    /**
     * CartRuleLoader applies the cart amount and currency thresholds.
     */
    public function isExempt(?CustomerEntity $customer, CountryEntity $deliveryCountry, string $salesChannelId): bool
    {
        if (!$this->appliesTo($customer, $deliveryCountry)) {
            return false;
        }

        if (!$deliveryCountry->getIsEu()) {
            return true;
        }

        return $this->hasIntraCommunityVatId($salesChannelId, $deliveryCountry, $customer, null);
    }

    public function getTaxCountryId(?CustomerEntity $customer, CountryEntity $deliveryCountry, string $salesChannelId): string
    {
        // Keep VAT-based country selection opt-in through the destination's B2B setting.
        if (!$this->appliesTo($customer, $deliveryCountry) || !$deliveryCountry->getIsEu()) {
            return $deliveryCountry->getId();
        }

        $vatIds = $this->resolveVatIds($salesChannelId, $deliveryCountry, $customer, null);
        if ($vatIds === []) {
            return $deliveryCountry->getId();
        }

        $dispatchCountryId = $this->resolveDispatchCountryId($salesChannelId, $deliveryCountry, $customer, null);
        $sellerState = $this->vatIdPatternProvider->getMemberStateOfCountry($dispatchCountryId);

        return $this->hasSellerStateVatId($vatIds, $sellerState) ? $dispatchCountryId : $deliveryCountry->getId();
    }

    /**
     * @internal
     *
     * Digital products are not shipped, so a cart of only digital products reads the billing country instead
     * opt-in like the intra EU exemption: a seller in the EU and a business customer billed in a member state
     * with Tax-free (B2B) enabled.
     */
    public function isTaxedByBillingCountry(?CustomerEntity $customer, CountryEntity $billingCountry, string $salesChannelId): bool
    {
        if (!$this->appliesTo($customer, $billingCountry) || !$billingCountry->getIsEu()) {
            return false;
        }

        return $this->vatIdPatternProvider->getMemberStateOfCountry(
            $this->resolveDispatchCountryId($salesChannelId, $billingCountry, $customer, null)
        ) !== null;
    }

    public function isIntraCommunityDelivery(OrderEntity $order): bool
    {
        $customer = $order->getOrderCustomer()?->getCustomer();
        if ($customer?->getAccountType() !== CustomerEntity::ACCOUNT_TYPE_BUSINESS) {
            return false;
        }

        $country = $this->getShippingAddress($order)?->getCountry();

        if ($country === null || !$country->getIsEu() || $order->getPrice()->getTaxStatus() !== CartPrice::TAX_STATE_FREE) {
            return false;
        }

        return $this->hasIntraCommunityVatId($order->getSalesChannelId(), $country, $customer, $order);
    }

    /**
     * @internal
     *
     * The buyer VAT ID an e-invoice states for an intra-community delivery: the first VAT ID that counts for the order
     * and starts with the code of a member state, in capitals, because EN 16931 rejects a VAT ID without it.
     */
    public function getIntraCommunityVatId(OrderEntity $order): ?string
    {
        $country = $this->getShippingAddress($order)?->getCountry();
        if ($country === null || !$this->isIntraCommunityDelivery($order)) {
            return null;
        }

        $vatIds = $this->resolveVatIds($order->getSalesChannelId(), $country, $order->getOrderCustomer()?->getCustomer(), $order);
        foreach ($vatIds as $vatId) {
            if ($this->vatIdPatternProvider->hasMemberStatePrefix($vatId)) {
                return strtoupper($vatId);
            }
        }

        return null;
    }

    /**
     * @internal
     *
     * The shipping address the intra-community decision for documents reads.
     */
    public function getShippingAddress(OrderEntity $order): ?OrderAddressEntity
    {
        // The ZUGFeRD credit note drops the deliveries of the order it bills, but keeps the primary one
        $delivery = Feature::isActive('v6.8.0.0')
            ? $order->getPrimaryOrderDelivery()
            : $order->getDeliveries()?->first() ?? $order->getPrimaryOrderDelivery();

        return $delivery?->getShippingOrderAddress();
    }

    public function reset(): void
    {
        $this->salesChannelCountryIds = [];
    }

    private function hasIntraCommunityVatId(string $salesChannelId, CountryEntity $deliveryCountry, ?CustomerEntity $customer, ?OrderEntity $order): bool
    {
        $vatIds = $this->resolveVatIds($salesChannelId, $deliveryCountry, $customer, $order);
        if ($vatIds === []) {
            return false;
        }

        $sellerState = $this->vatIdPatternProvider->getMemberStateOfCountry(
            $this->resolveDispatchCountryId($salesChannelId, $deliveryCountry, $customer, $order)
        );
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

    private function appliesTo(?CustomerEntity $customer, CountryEntity $deliveryCountry): bool
    {
        return $customer?->getAccountType() === CustomerEntity::ACCOUNT_TYPE_BUSINESS && $deliveryCountry->getCompanyTax()->getEnabled();
    }

    /**
     * @return list<string>
     */
    private function resolveVatIds(string $salesChannelId, CountryEntity $taxCountry, ?CustomerEntity $customer, ?OrderEntity $order): array
    {
        return $this->extensions->publish(
            name: ResolveVatIdsExtension::NAME,
            extension: new ResolveVatIdsExtension($salesChannelId, $taxCountry, $customer, $order),
            function: fn (string $salesChannelId, CountryEntity $taxCountry, ?CustomerEntity $customer, ?OrderEntity $order): array => $this->getVatIds($customer, $order),
        );
    }

    /**
     * Documents read the VAT IDs saved on the order instead of the customer's current ones.
     *
     * @return list<string>
     */
    private function getVatIds(?CustomerEntity $customer, ?OrderEntity $order): array
    {
        $vatIds = $order !== null ? $order->getOrderCustomer()?->getVatIds() : $customer?->getVatIds();

        return array_values(array_filter($vatIds ?? []));
    }

    private function resolveDispatchCountryId(string $salesChannelId, CountryEntity $taxCountry, ?CustomerEntity $customer, ?OrderEntity $order): string
    {
        return strtolower($this->extensions->publish(
            name: ResolveDispatchCountryExtension::NAME,
            extension: new ResolveDispatchCountryExtension($salesChannelId, $taxCountry, $customer, $order),
            function: fn (string $salesChannelId, CountryEntity $taxCountry, ?CustomerEntity $customer, ?OrderEntity $order): string => $this->getSellerCountryId($salesChannelId),
        ));
    }

    private function getSellerCountryId(string $salesChannelId): string
    {
        // Reading the config of an empty sales channel throws
        if ($salesChannelId === '') {
            return '';
        }

        $countryId = $this->systemConfigService->getString(self::SELLER_COUNTRY_CONFIG_KEY, $salesChannelId);

        return $countryId !== '' ? $countryId : $this->getSalesChannelCountryId($salesChannelId);
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
