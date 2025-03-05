<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Context;

use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateCollection;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateEntity;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Currency\Aggregate\CurrencyCountryRounding\CurrencyCountryRoundingCollection;
use Shopware\Core\System\Currency\Aggregate\CurrencyCountryRounding\CurrencyCountryRoundingEntity;
use Shopware\Core\System\Currency\CurrencyCollection;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\SalesChannel\BaseSalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\SalesChannel\SalesChannelException;
use Shopware\Core\System\Tax\TaxCollection;

/**
 * @internal
 */
#[Package('framework')]
class BaseSalesChannelContextFactory extends AbstractBaseSalesChannelContextFactory
{
    /**
     * @param EntityRepository<SalesChannelCollection> $salesChannelRepository
     * @param EntityRepository<CurrencyCollection> $currencyRepository
     * @param EntityRepository<CustomerGroupCollection> $customerGroupRepository
     * @param EntityRepository<CountryCollection> $countryRepository
     * @param EntityRepository<TaxCollection> $taxRepository
     * @param EntityRepository<PaymentMethodCollection> $paymentMethodRepository
     * @param EntityRepository<ShippingMethodCollection> $shippingMethodRepository
     * @param EntityRepository<CountryStateCollection> $countryStateRepository
     * @param EntityRepository<CurrencyCountryRoundingCollection> $currencyCountryRepository
     */
    public function __construct(
        private readonly EntityRepository $salesChannelRepository,
        private readonly EntityRepository $currencyRepository,
        private readonly EntityRepository $customerGroupRepository,
        private readonly EntityRepository $countryRepository,
        private readonly EntityRepository $taxRepository,
        private readonly EntityRepository $paymentMethodRepository,
        private readonly EntityRepository $shippingMethodRepository,
        private readonly EntityRepository $countryStateRepository,
        private readonly EntityRepository $currencyCountryRepository,
        private readonly ContextFactory $contextFactory,
    ) {
    }

    /**
     * @param array<string, mixed> $options
     */
    public function create(string $salesChannelId, array $options = []): BaseSalesChannelContext
    {
        $context = $this->contextFactory->getContext($salesChannelId, $options);

        $criteria = new Criteria([$salesChannelId]);
        $criteria->setTitle('base-context-factory::sales-channel');
        $criteria->addAssociation('currency');
        $criteria->addAssociation('domains');
        $criteria->getAssociation('languages')
            ->addFilter(new EqualsFilter('id', $context->getLanguageId()))
            ->addAssociation('translationCode')
            ->addAssociation('locale');

        $salesChannel = $this->salesChannelRepository->search($criteria, $context)->getEntities()->get($salesChannelId);
        if (!$salesChannel instanceof SalesChannelEntity) {
            throw SalesChannelException::salesChannelNotFound($salesChannelId);
        }

        // load active currency, fallback to shop currency
        $currency = $salesChannel->getCurrency();
        if (\array_key_exists(SalesChannelContextService::CURRENCY_ID, $options)) {
            $currencyId = $options[SalesChannelContextService::CURRENCY_ID];
            if (!\is_string($currencyId) || !Uuid::isValid($currencyId)) {
                throw SalesChannelException::invalidCurrencyId();
            }

            $criteria = new Criteria([$currencyId]);
            $criteria->setTitle('base-context-factory::currency');

            $currency = $this->currencyRepository->search($criteria, $context)->get($currencyId);

            if (!$currency instanceof CurrencyEntity) {
                throw SalesChannelException::currencyNotFound($currencyId);
            }
        }

        if ($currency === null) {
            throw SalesChannelException::currencyNotFound($salesChannel->getCurrencyId());
        }

        // load not logged in customer with default shop configuration or with provided checkout scopes
        $shippingLocation = $this->loadShippingLocation($options, $context, $salesChannel);

        $groupId = $salesChannel->getCustomerGroupId();

        $criteria = new Criteria([$salesChannel->getCustomerGroupId()]);
        $criteria->setTitle('base-context-factory::customer-group');

        $customerGroup = $this->customerGroupRepository->search($criteria, $context)->getEntities()->get($groupId);
        if ($customerGroup === null) {
            throw SalesChannelException::customerGroupNotFound($groupId);
        }

        // loads tax rules based on active customer and delivery address
        $taxRules = $this->getTaxRules($context);

        // detect active payment method, first check if checkout defined other payment method, otherwise validate if customer logged in, at least use shop default
        $payment = $this->getPaymentMethod($options, $context, $salesChannel);

        // detect active delivery method, at first checkout scope, at least shop default method
        $shippingMethod = $this->getShippingMethod($options, $context, $salesChannel);

        [$itemRounding, $totalRounding] = $this->getCashRounding($currency, $shippingLocation, $context);

        $context = new Context(
            $context->getSource(),
            [],
            $currency->getId(),
            $context->getLanguageIdChain(),
            $context->getVersionId(),
            $currency->getFactor(),
            true,
            CartPrice::TAX_STATE_GROSS,
            $itemRounding
        );

        return new BaseSalesChannelContext(
            $context,
            $salesChannel,
            $currency,
            $customerGroup,
            $taxRules,
            $payment,
            $shippingMethod,
            $shippingLocation,
            $itemRounding,
            $totalRounding,
            $this->getLanguageInfo($salesChannel->getLanguages(), $context->getLanguageId()),
        );
    }

    private function getTaxRules(Context $context): TaxCollection
    {
        $criteria = new Criteria();
        $criteria->setTitle('base-context-factory::taxes');
        $criteria->addAssociation('rules.type');

        return $this->taxRepository->search($criteria, $context)->getEntities();
    }

    /**
     * @param array<string, mixed> $options
     */
    private function getPaymentMethod(array $options, Context $context, SalesChannelEntity $salesChannel): PaymentMethodEntity
    {
        $id = $options[SalesChannelContextService::PAYMENT_METHOD_ID] ?? $salesChannel->getPaymentMethodId();

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('media');
        $criteria->addAssociation('appPaymentMethod');
        $criteria->setTitle('base-context-factory::payment-method');

        $paymentMethod = $this->paymentMethodRepository
            ->search($criteria, $context)
            ->get($id);

        if (!$paymentMethod instanceof PaymentMethodEntity) {
            throw SalesChannelException::unknownPaymentMethod($id);
        }

        return $paymentMethod;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function getShippingMethod(array $options, Context $context, SalesChannelEntity $salesChannel): ShippingMethodEntity
    {
        $id = $options[SalesChannelContextService::SHIPPING_METHOD_ID] ?? $salesChannel->getShippingMethodId();

        $ids = \array_unique(array_filter([$id, $salesChannel->getShippingMethodId()]));

        $criteria = new Criteria($ids);
        $criteria->addAssociation('media');
        $criteria->setTitle('base-context-factory::shipping-method');

        $shippingMethods = $this->shippingMethodRepository->search($criteria, $context)->getEntities();

        $shippingMethod = $shippingMethods->get($id) ?? $shippingMethods->get($salesChannel->getShippingMethodId());
        if ($shippingMethod === null) {
            throw SalesChannelException::shippingMethodNotFound($id);
        }

        return $shippingMethod;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function loadShippingLocation(array $options, Context $context, SalesChannelEntity $salesChannel): ShippingLocation
    {
        // allows previewing cart calculation for a specify state for not logged in customers
        if (isset($options[SalesChannelContextService::COUNTRY_STATE_ID])) {
            $countryStateId = $options[SalesChannelContextService::COUNTRY_STATE_ID];
            if (!\is_string($countryStateId) || !Uuid::isValid($countryStateId)) {
                throw SalesChannelException::invalidCountryStateId();
            }

            $criteria = new Criteria([$countryStateId]);
            $criteria->addAssociation('country');

            $criteria->setTitle('base-context-factory::country');

            $state = $this->countryStateRepository->search($criteria, $context)->get($countryStateId);

            if (!$state instanceof CountryStateEntity) {
                throw SalesChannelException::countryStateNotFound($countryStateId);
            }

            $country = $state->getCountry();
            if (!$country instanceof CountryEntity) {
                throw SalesChannelException::countryNotFound($state->getCountryId());
            }

            return new ShippingLocation($country, $state, null);
        }

        $countryId = $options[SalesChannelContextService::COUNTRY_ID] ?? $salesChannel->getCountryId();
        if (!\is_string($countryId) || !Uuid::isValid($countryId)) {
            throw SalesChannelException::invalidCountryId();
        }

        $criteria = new Criteria([$countryId]);
        $criteria->setTitle('base-context-factory::country');

        $country = $this->countryRepository->search($criteria, $context)->get($countryId);

        if (!$country instanceof CountryEntity) {
            throw SalesChannelException::countryNotFound($countryId);
        }

        return ShippingLocation::createFromCountry($country);
    }

    /**
     * @return CashRoundingConfig[]
     */
    private function getCashRounding(CurrencyEntity $currency, ShippingLocation $shippingLocation, Context $context): array
    {
        $criteria = new Criteria();
        $criteria->setTitle('base-context-factory::cash-rounding');
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('currencyId', $currency->getId()));
        $criteria->addFilter(new EqualsFilter('countryId', $shippingLocation->getCountry()->getId()));

        $countryConfig = $this->currencyCountryRepository->search($criteria, $context)->first();

        if ($countryConfig instanceof CurrencyCountryRoundingEntity) {
            return [$countryConfig->getItemRounding(), $countryConfig->getTotalRounding()];
        }

        return [$currency->getItemRounding(), $currency->getTotalRounding()];
    }

    private function getLanguageInfo(?LanguageCollection $languages, string $currentLanguageId): LanguageInfo
    {
        $currentLanguage = $languages?->get($currentLanguageId);
        if ($currentLanguage === null) {
            throw SalesChannelException::languageNotFound($currentLanguageId);
        }

        $locale = $currentLanguage->getTranslationCode() ?? $currentLanguage->getLocale();
        \assert($locale !== null, 'At least the localeId is required, so the fallback should never be null');

        return new LanguageInfo(
            $currentLanguage->getTranslation('name') ?? $currentLanguage->getName(),
            $locale->getCode(),
        );
    }
}
