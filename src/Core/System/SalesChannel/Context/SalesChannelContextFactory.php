<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Context;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\Tax\TaxDetector;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\Exception\LanguageNotFoundException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateEntity;
use Shopware\Core\System\SalesChannel\Event\SalesChannelContextPermissionsChangedEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\Tax\Aggregate\TaxRule\TaxRuleCollection;
use Shopware\Core\System\Tax\Aggregate\TaxRule\TaxRuleEntity;
use Shopware\Core\System\Tax\TaxCollection;
use Shopware\Core\System\Tax\TaxEntity;
use Shopware\Core\System\Tax\TaxRuleType\TaxRuleTypeFilterInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class SalesChannelContextFactory
{
    /**
     * @var EntityRepositoryInterface
     */
    private $salesChannelRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $currencyRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $customerGroupRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $countryRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $taxRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $addressRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $paymentMethodRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $shippingMethodRepository;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var EntityRepositoryInterface
     */
    private $countryStateRepository;

    /**
     * @var TaxDetector
     */
    private $taxDetector;

    /**
     * @var iterable|TaxRuleTypeFilterInterface[]
     */
    private $taxRuleTypeFilter;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        EntityRepositoryInterface $salesChannelRepository,
        EntityRepositoryInterface $currencyRepository,
        EntityRepositoryInterface $customerRepository,
        EntityRepositoryInterface $customerGroupRepository,
        EntityRepositoryInterface $countryRepository,
        EntityRepositoryInterface $taxRepository,
        EntityRepositoryInterface $addressRepository,
        EntityRepositoryInterface $paymentMethodRepository,
        EntityRepositoryInterface $shippingMethodRepository,
        Connection $connection,
        EntityRepositoryInterface $countryStateRepository,
        TaxDetector $taxDetector,
        iterable $taxRuleTypeFilter,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->salesChannelRepository = $salesChannelRepository;
        $this->currencyRepository = $currencyRepository;
        $this->customerRepository = $customerRepository;
        $this->customerGroupRepository = $customerGroupRepository;
        $this->countryRepository = $countryRepository;
        $this->taxRepository = $taxRepository;
        $this->addressRepository = $addressRepository;
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->shippingMethodRepository = $shippingMethodRepository;
        $this->connection = $connection;
        $this->countryStateRepository = $countryStateRepository;
        $this->taxDetector = $taxDetector;
        $this->taxRuleTypeFilter = $taxRuleTypeFilter;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function create(string $token, string $salesChannelId, array $options = []): SalesChannelContext
    {
        $context = $this->getContext($salesChannelId, $options);

        $criteria = new Criteria([$salesChannelId]);
        $criteria->setTitle('context-factory::sales-channel');
        $criteria->addAssociation('currency');
        $criteria->addAssociation('domains');

        /** @var SalesChannelEntity|null $salesChannel */
        $salesChannel = $this->salesChannelRepository->search($criteria, $context)
            ->get($salesChannelId);

        if (!$salesChannel) {
            throw new \RuntimeException(sprintf('Sales channel with id %s not found or not valid!', $salesChannelId));
        }

        //load active language, fallback to shop language
        if (array_key_exists(SalesChannelContextService::LANGUAGE_ID, $options)) {
            $salesChannel->setLanguageId($options[SalesChannelContextService::LANGUAGE_ID]);
        }

        //load active currency, fallback to shop currency
        $currency = $salesChannel->getCurrency();
        if (array_key_exists(SalesChannelContextService::CURRENCY_ID, $options)) {
            $currencyId = $options[SalesChannelContextService::CURRENCY_ID];

            $criteria = new Criteria([$currencyId]);
            $criteria->setTitle('context-factory::currency');
            $currency = $this->currencyRepository->search($criteria, $context)->get($currencyId);
        }

        // customer
        $customer = null;
        if (array_key_exists(SalesChannelContextService::CUSTOMER_ID, $options) && $options[SalesChannelContextService::CUSTOMER_ID] !== null) {
            //load logged in customer and set active addresses
            $customer = $this->loadCustomer($options, $context);
        }

        $shippingLocation = null;
        if ($customer) {
            $shippingLocation = ShippingLocation::createFromAddress($customer->getActiveShippingAddress());
        }

        if (!$shippingLocation) {
            //load not logged in customer with default shop configuration or with provided checkout scopes
            $shippingLocation = $this->loadShippingLocation($options, $context, $salesChannel);
        }

        $groupId = $salesChannel->getCustomerGroupId();
        $groupIds = [$salesChannel->getCustomerGroupId(), Defaults::FALLBACK_CUSTOMER_GROUP];

        if ($customer) {
            $groupIds[] = $customer->getGroupId();
            $groupId = $customer->getGroupId();
        }

        $groupIds = array_keys(array_flip($groupIds));

        //fallback customer group is hard coded to 'EK'
        $criteria = new Criteria($groupIds);
        $criteria->setTitle('context-factory::customer-group');

        $customerGroups = $this->customerGroupRepository->search($criteria, $context);
        $fallbackGroup = $customerGroups->get(Defaults::FALLBACK_CUSTOMER_GROUP);
        $customerGroup = $customerGroups->get($groupId);

        //loads tax rules based on active customer and delivery address
        $taxRules = $this->getTaxRules($context, $customer, $shippingLocation);

        //detect active payment method, first check if checkout defined other payment method, otherwise validate if customer logged in, at least use shop default
        $payment = $this->getPaymentMethod($options, $context, $salesChannel, $customer);

        //detect active delivery method, at first checkout scope, at least shop default method
        $shippingMethod = $this->getShippingMethod($options, $context, $salesChannel);

        $context = new Context(
            $context->getSource(),
            [],
            $currency->getId(),
            $context->getLanguageIdChain(),
            $context->getVersionId(),
            $currency->getFactor(),
            $currency->getDecimalPrecision(),
            true
        );

        $salesChannelContext = new SalesChannelContext(
            $context,
            $token,
            $salesChannel,
            $currency,
            $customerGroup,
            $fallbackGroup,
            $taxRules,
            $payment,
            $shippingMethod,
            $shippingLocation,
            $customer,
            []
        );

        if (array_key_exists(SalesChannelContextService::PERMISSIONS, $options)) {
            $salesChannelContext->setPermissions($options[SalesChannelContextService::PERMISSIONS]);

            $event = new SalesChannelContextPermissionsChangedEvent($salesChannelContext, $options[SalesChannelContextService::PERMISSIONS]);
            $this->eventDispatcher->dispatch($event);

            $salesChannelContext->lockPermissions();
        }

        $salesChannelContext->setTaxState($this->taxDetector->getTaxState($salesChannelContext));

        return $salesChannelContext;
    }

    public function getTaxRules(Context $context, ?CustomerEntity $customer, ShippingLocation $shippingLocation): TaxCollection
    {
        $criteria = new Criteria();
        $criteria->setTitle('context-factory::taxes');
        $criteria->addAssociation('rules.type');
        $criteria->addExtension('test', new Criteria());

        $taxes = $this->taxRepository->search($criteria, $context)->getEntities();

        /** @var TaxEntity $tax */
        foreach ($taxes as $tax) {
            $taxRules = $tax->getRules()->filter(function (TaxRuleEntity $taxRule) use ($customer, $shippingLocation) {
                foreach ($this->taxRuleTypeFilter as $ruleTypeFilter) {
                    if ($ruleTypeFilter->match($taxRule, $customer, $shippingLocation)) {
                        return true;
                    }
                }

                return false;
            });
            $taxRules->sortByTypePosition();
            $taxRule = $taxRules->first();

            $matchingRules = new TaxRuleCollection();
            if ($taxRule) {
                $matchingRules->add($taxRule);
            }
            $tax->setRules($matchingRules);
        }

        return new TaxCollection($taxes);
    }

    private function getPaymentMethod(array $options, Context $context, SalesChannelEntity $salesChannel, ?CustomerEntity $customer): PaymentMethodEntity
    {
        $id = $salesChannel->getPaymentMethodId();

        if (array_key_exists(SalesChannelContextService::PAYMENT_METHOD_ID, $options)) {
            $id = $options[SalesChannelContextService::PAYMENT_METHOD_ID];
        } elseif ($customer && $customer->getLastPaymentMethodId()) {
            $id = $customer->getLastPaymentMethodId();
        } elseif ($customer && $customer->getDefaultPaymentMethodId()) {
            $id = $customer->getDefaultPaymentMethodId();
        }

        $criteria = (new Criteria([$id]))->addAssociation('media');
        $criteria->setTitle('context-factory::payment-method');

        return $this->paymentMethodRepository
            ->search($criteria, $context)
            ->get($id);
    }

    private function getShippingMethod(array $options, Context $context, SalesChannelEntity $salesChannel): ShippingMethodEntity
    {
        $id = $salesChannel->getShippingMethodId();

        if (array_key_exists(SalesChannelContextService::SHIPPING_METHOD_ID, $options)) {
            $id = $options[SalesChannelContextService::SHIPPING_METHOD_ID];
        }

        $criteria = (new Criteria([$id]))->addAssociation('media');
        $criteria->setTitle('context-factory::shipping-method');

        return $this->shippingMethodRepository->search($criteria, $context)->get($id);
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
          currency.decimal_precision as sales_channel_currency_decimal_precision,
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

        $origin = new SalesChannelApiSource($salesChannelId);

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
            (int) $data['sales_channel_currency_decimal_precision'],
            true
        );
    }

    private function getParentLanguageId(string $languageId): ?string
    {
        if (!$languageId || !Uuid::isValid($languageId)) {
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

    private function loadCustomer(array $options, Context $context): ?CustomerEntity
    {
        $customerId = $options[SalesChannelContextService::CUSTOMER_ID];

        $criteria = new Criteria([$customerId]);
        $criteria->setTitle('context-factory::customer');
        $criteria->addAssociation('salutation');
        $criteria->addAssociation('defaultPaymentMethod');
        $criteria->addAssociation('defaultBillingAddress.country');
        $criteria->addAssociation('defaultBillingAddress.countryState');
        $criteria->addAssociation('defaultShippingAddress.country');
        $criteria->addAssociation('defaultShippingAddress.countryState');

        $customer = $this->customerRepository->search($criteria, $context)->get($customerId);

        if (!$customer) {
            return null;
        }

        $billingAddressId = $options[SalesChannelContextService::BILLING_ADDRESS_ID] ?? $customer->getDefaultBillingAddressId();
        $shippingAddressId = $options[SalesChannelContextService::SHIPPING_ADDRESS_ID] ?? $customer->getDefaultShippingAddressId();

        $addressIds[] = $billingAddressId;
        $addressIds[] = $shippingAddressId;

        $criteria = new Criteria($addressIds);
        $criteria->setTitle('context-factory::addresses');
        $criteria->addAssociation('country');
        $criteria->addAssociation('countryState');

        $addresses = $this->addressRepository->search($criteria, $context);

        $customer->setActiveBillingAddress($addresses->get($billingAddressId));
        $customer->setActiveShippingAddress($addresses->get($shippingAddressId));

        return $customer;
    }

    private function loadShippingLocation(
        array $options,
        Context $context,
        SalesChannelEntity $salesChannel
    ): ShippingLocation {
        //allows to preview cart calculation for a specify state for not logged in customers
        if (array_key_exists(SalesChannelContextService::COUNTRY_STATE_ID, $options) && $options[SalesChannelContextService::COUNTRY_STATE_ID]) {
            $criteria = new Criteria([$options[SalesChannelContextService::COUNTRY_STATE_ID]]);
            $criteria->addAssociation('country');

            $criteria->setTitle('context-factory::country');

            $state = $this->countryStateRepository->search($criteria, $context)
                ->get($options[SalesChannelContextService::COUNTRY_STATE_ID]);

            /* @var CountryStateEntity $state */
            return new ShippingLocation($state->getCountry(), $state, null);
        }

        $countryId = $salesChannel->getCountryId();
        if (array_key_exists(SalesChannelContextService::COUNTRY_ID, $options) && $options[SalesChannelContextService::COUNTRY_ID]) {
            $countryId = $options[SalesChannelContextService::COUNTRY_ID];
        }

        $criteria = new Criteria([$countryId]);
        $criteria->setTitle('context-factory::country');

        $country = $this->countryRepository->search($criteria, $context)
            ->get($countryId);

        return ShippingLocation::createFromCountry($country);
    }

    private function buildLanguageChain(array $sessionOptions, string $defaultLanguageId, array $availableLanguageIds): array
    {
        $current = $sessionOptions[SalesChannelContextService::LANGUAGE_ID] ?? $defaultLanguageId;

        //check provided language is part of the available languages
        if (!\in_array($current, $availableLanguageIds, true)) {
            throw new \RuntimeException('Provided language is not available');
        }

        if ($current === Defaults::LANGUAGE_SYSTEM) {
            return [Defaults::LANGUAGE_SYSTEM];
        }

        //provided language can be a child language
        return [$current, $this->getParentLanguageId($current), Defaults::LANGUAGE_SYSTEM];
    }
}
