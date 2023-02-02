<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Context;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Payment\Exception\UnknownPaymentMethodException;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\AdminSalesChannelApiSource;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Exception\LanguageNotFoundException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\Aggregate\CurrencyCountryRounding\CurrencyCountryRoundingEntity;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SalesChannel\BaseContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\Tax\TaxCollection;
use function array_unique;

/**
 * @internal
 */
class BaseContextFactory extends AbstractBaseContextFactory
{
    private EntityRepositoryInterface $salesChannelRepository;

    private EntityRepositoryInterface $currencyRepository;

    private EntityRepositoryInterface $customerGroupRepository;

    private EntityRepositoryInterface $countryRepository;

    private EntityRepositoryInterface $taxRepository;

    private EntityRepositoryInterface $paymentMethodRepository;

    private EntityRepositoryInterface $shippingMethodRepository;

    private Connection $connection;

    private EntityRepositoryInterface $countryStateRepository;

    private EntityRepositoryInterface $currencyCountryRepository;

    public function __construct(
        EntityRepositoryInterface $salesChannelRepository,
        EntityRepositoryInterface $currencyRepository,
        EntityRepositoryInterface $customerGroupRepository,
        EntityRepositoryInterface $countryRepository,
        EntityRepositoryInterface $taxRepository,
        EntityRepositoryInterface $paymentMethodRepository,
        EntityRepositoryInterface $shippingMethodRepository,
        Connection $connection,
        EntityRepositoryInterface $countryStateRepository,
        EntityRepositoryInterface $currencyCountryRepository
    ) {
        $this->salesChannelRepository = $salesChannelRepository;
        $this->currencyRepository = $currencyRepository;
        $this->countryRepository = $countryRepository;
        $this->taxRepository = $taxRepository;
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->shippingMethodRepository = $shippingMethodRepository;
        $this->connection = $connection;
        $this->countryStateRepository = $countryStateRepository;
        $this->currencyCountryRepository = $currencyCountryRepository;
        $this->customerGroupRepository = $customerGroupRepository;
    }

    public function getDecorated(): AbstractBaseContextFactory
    {
        throw new DecorationPatternException(self::class);
    }

    public function create(string $salesChannelId, array $options = []): BaseContext
    {
        $context = $this->getContext($salesChannelId, $options);

        $criteria = new Criteria([$salesChannelId]);
        $criteria->setTitle('base-context-factory::sales-channel');
        $criteria->addAssociation('currency');
        $criteria->addAssociation('domains');

        /** @var SalesChannelEntity|null $salesChannel */
        $salesChannel = $this->salesChannelRepository->search($criteria, $context)
            ->get($salesChannelId);

        if (!$salesChannel) {
            throw new \RuntimeException(sprintf('Sales channel with id %s not found or not valid!', $salesChannelId));
        }

        if (!Feature::isActive('FEATURE_NEXT_17276')) {
            /*
             * @deprecated tag:v6.5.0 - Overriding the languageId of the SalesChannel is deprecated and will be removed in v6.5.0
             * use `$salesChannelContext->getLanguageId()` instead
             */
            if (\array_key_exists(SalesChannelContextService::LANGUAGE_ID, $options)) {
                $salesChannel->setLanguageId($options[SalesChannelContextService::LANGUAGE_ID]);
            }
        }

        //load active currency, fallback to shop currency
        $currency = $salesChannel->getCurrency();
        if (\array_key_exists(SalesChannelContextService::CURRENCY_ID, $options)) {
            $currencyId = $options[SalesChannelContextService::CURRENCY_ID];

            $criteria = new Criteria([$currencyId]);
            $criteria->setTitle('base-context-factory::currency');
            $currency = $this->currencyRepository->search($criteria, $context)->get($currencyId);
        }

        //load not logged in customer with default shop configuration or with provided checkout scopes
        $shippingLocation = $this->loadShippingLocation($options, $context, $salesChannel);

        $groupId = $salesChannel->getCustomerGroupId();

        /** @deprecated tag:v6.5.0 - Fallback customer group is deprecated and will be removed */
        $groupIds = array_unique([$salesChannel->getCustomerGroupId(), Defaults::FALLBACK_CUSTOMER_GROUP]);

        $criteria = new Criteria($groupIds);
        $criteria->setTitle('base-context-factory::customer-group');

        $customerGroups = $this->customerGroupRepository->search($criteria, $context);

        /** @deprecated tag:v6.5.0 - Fallback customer group is deprecated and will be removed */
        $fallbackGroup = $customerGroups->has(Defaults::FALLBACK_CUSTOMER_GROUP) ? $customerGroups->get(Defaults::FALLBACK_CUSTOMER_GROUP) : $customerGroups->get($salesChannel->getCustomerGroupId());
        $customerGroup = $customerGroups->get($groupId);

        //loads tax rules based on active customer and delivery address
        $taxRules = $this->getTaxRules($context);

        //detect active payment method, first check if checkout defined other payment method, otherwise validate if customer logged in, at least use shop default
        $payment = $this->getPaymentMethod($options, $context, $salesChannel);

        //detect active delivery method, at first checkout scope, at least shop default method
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

        return new BaseContext(
            $context,
            $salesChannel,
            $currency,
            $customerGroup,
            $fallbackGroup,
            $taxRules,
            $payment,
            $shippingMethod,
            $shippingLocation,
            $itemRounding,
            $totalRounding
        );
    }

    private function getTaxRules(Context $context): TaxCollection
    {
        $criteria = new Criteria();
        $criteria->setTitle('base-context-factory::taxes');
        $criteria->addAssociation('rules.type');

        $taxes = $this->taxRepository->search($criteria, $context)->getEntities();

        return new TaxCollection($taxes);
    }

    private function getPaymentMethod(array $options, Context $context, SalesChannelEntity $salesChannel): PaymentMethodEntity
    {
        $id = $options[SalesChannelContextService::PAYMENT_METHOD_ID] ?? $salesChannel->getPaymentMethodId();

        $criteria = (new Criteria([$id]))->addAssociation('media');
        $criteria->setTitle('base-context-factory::payment-method');

        $paymentMethod = $this->paymentMethodRepository
            ->search($criteria, $context)
            ->get($id);

        if (!$paymentMethod) {
            throw new UnknownPaymentMethodException($id);
        }

        return $paymentMethod;
    }

    private function getShippingMethod(array $options, Context $context, SalesChannelEntity $salesChannel): ShippingMethodEntity
    {
        $id = $options[SalesChannelContextService::SHIPPING_METHOD_ID] ?? $salesChannel->getShippingMethodId();

        $ids = array_unique(array_filter([$id, $salesChannel->getShippingMethodId()]));

        $criteria = new Criteria($ids);
        $criteria->addAssociation('media');
        $criteria->setTitle('base-context-factory::shipping-method');

        $shippingMethods = $this->shippingMethodRepository->search($criteria, $context);

        return $shippingMethods->get($id) ?? $shippingMethods->get($salesChannel->getShippingMethodId());
    }

    private function getContext(string $salesChannelId, array $session): Context
    {
        $sql = '
        # context-factory::base-context

        SELECT
          sales_channel.id as sales_channel_id,
          sales_channel.language_id as sales_channel_default_language_id,
          sales_channel.currency_id as sales_channel_currency_id,
          currency.factor as sales_channel_currency_factor,
          GROUP_CONCAT(LOWER(HEX(sales_channel_language.language_id))) as sales_channel_language_ids
        FROM sales_channel
            INNER JOIN currency
                ON sales_channel.currency_id = currency.id
            LEFT JOIN sales_channel_language
                ON sales_channel_language.sales_channel_id = sales_channel.id
        WHERE sales_channel.id = :id
        GROUP BY sales_channel.id, sales_channel.language_id, sales_channel.currency_id, currency.factor';

        $data = $this->connection->fetchAssoc($sql, [
            'id' => Uuid::fromHexToBytes($salesChannelId),
        ]);
        if ($data === false) {
            throw new \RuntimeException(sprintf('No context data found for SalesChannel "%s"', $salesChannelId));
        }

        if (isset($session[SalesChannelContextService::ORIGINAL_CONTEXT])) {
            $origin = new AdminSalesChannelApiSource($salesChannelId, $session[SalesChannelContextService::ORIGINAL_CONTEXT]);
        } else {
            $origin = new SalesChannelApiSource($salesChannelId);
        }

        //explode all available languages for the provided sales channel
        $languageIds = $data['sales_channel_language_ids'] ? explode(',', $data['sales_channel_language_ids']) : [];
        $languageIds = array_keys(array_flip($languageIds));

        //check which language should be used in the current request (request header set, or context already contains a language - stored in `sales_channel_api_context`)
        $defaultLanguageId = Uuid::fromBytesToHex($data['sales_channel_default_language_id']);

        $languageChain = $this->buildLanguageChain($session, $defaultLanguageId, $languageIds);

        $versionId = Defaults::LIVE_VERSION;
        if (isset($session[SalesChannelContextService::VERSION_ID])) {
            $versionId = $session[SalesChannelContextService::VERSION_ID];
        }

        return new Context(
            $origin,
            [],
            Uuid::fromBytesToHex($data['sales_channel_currency_id']),
            $languageChain,
            $versionId,
            (float) $data['sales_channel_currency_factor'],
            true
        );
    }

    private function getParentLanguageId(string $languageId): ?string
    {
        if (!Uuid::isValid($languageId)) {
            throw new LanguageNotFoundException($languageId);
        }

        $data = $this->connection->createQueryBuilder()
            ->select(['LOWER(HEX(language.parent_id))'])
            ->from('language')
            ->where('language.id = :id')
            ->setParameter('id', Uuid::fromHexToBytes($languageId))
            ->execute()
            ->fetchColumn();

        if ($data === false) {
            throw new LanguageNotFoundException($languageId);
        }

        return $data;
    }

    private function loadShippingLocation(array $options, Context $context, SalesChannelEntity $salesChannel): ShippingLocation
    {
        //allows previewing cart calculation for a specify state for not logged in customers
        if (isset($options[SalesChannelContextService::COUNTRY_STATE_ID])) {
            $criteria = new Criteria([$options[SalesChannelContextService::COUNTRY_STATE_ID]]);
            $criteria->addAssociation('country');

            $criteria->setTitle('base-context-factory::country');

            $state = $this->countryStateRepository->search($criteria, $context)
                ->get($options[SalesChannelContextService::COUNTRY_STATE_ID]);

            return new ShippingLocation($state->getCountry(), $state, null);
        }

        $countryId = $options[SalesChannelContextService::COUNTRY_ID] ?? $salesChannel->getCountryId();

        $criteria = new Criteria([$countryId]);
        $criteria->setTitle('base-context-factory::country');

        $country = $this->countryRepository->search($criteria, $context)->get($countryId);

        return ShippingLocation::createFromCountry($country);
    }

    /**
     * @param array<string> $availableLanguageIds
     *
     * @return non-empty-array<string>
     */
    private function buildLanguageChain(array $sessionOptions, string $defaultLanguageId, array $availableLanguageIds): array
    {
        $current = $sessionOptions[SalesChannelContextService::LANGUAGE_ID] ?? $defaultLanguageId;

        //check provided language is part of the available languages
        if (!\in_array($current, $availableLanguageIds, true)) {
            throw new \RuntimeException(
                sprintf('Provided language %s is not in list of available languages: %s', $current, implode(', ', $availableLanguageIds))
            );
        }

        if ($current === Defaults::LANGUAGE_SYSTEM) {
            return [Defaults::LANGUAGE_SYSTEM];
        }

        //provided language can be a child language
        return array_filter([$current, $this->getParentLanguageId($current), Defaults::LANGUAGE_SYSTEM]);
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

        /** @var CurrencyCountryRoundingEntity|null $countryConfig */
        $countryConfig = $this->currencyCountryRepository->search($criteria, $context)->first();

        if ($countryConfig) {
            return [$countryConfig->getItemRounding(), $countryConfig->getTotalRounding()];
        }

        return [$currency->getItemRounding(), $currency->getTotalRounding()];
    }
}
