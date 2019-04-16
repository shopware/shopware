<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Context;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\Tax\TaxDetector;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Context\SalesChannelApiSource;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\Exception\LanguageNotFoundException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\Tax\TaxCollection;

class SalesChannelContextFactory implements SalesChannelContextFactoryInterface
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
        TaxDetector $taxDetector
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
    }

    public function create(string $token, string $salesChannelId, array $options = []): SalesChannelContext
    {
        $context = $this->getContext($salesChannelId, $options);

        /** @var SalesChannelEntity|null $salesChannel */
        $salesChannel = $this->salesChannelRepository->search(new Criteria([$salesChannelId]), $context)
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
            $currency = $this->currencyRepository->search(new Criteria([$options[SalesChannelContextService::CURRENCY_ID]]), $context)->get($options[SalesChannelContextService::CURRENCY_ID]);
        }

        //fallback customer group is hard coded to 'EK'
        $customerGroups = $this->customerGroupRepository->search(new Criteria([Defaults::FALLBACK_CUSTOMER_GROUP]), $context);
        $fallbackGroup = $customerGroups->get(Defaults::FALLBACK_CUSTOMER_GROUP);
        $customerGroup = $customerGroups->get(Defaults::FALLBACK_CUSTOMER_GROUP);

        // customer
        $customer = null;
        if (array_key_exists(SalesChannelContextService::CUSTOMER_ID, $options)) {
            //load logged in customer and set active addresses
            $customer = $this->loadCustomer($options, $context);
        }

        $shippingLocation = null;
        if ($customer) {
            $shippingLocation = ShippingLocation::createFromAddress($customer->getActiveShippingAddress());
            $customerGroup = $customer->getGroup();
        }

        if (!$shippingLocation) {
            //load not logged in customer with default shop configuration or with provided checkout scopes
            $shippingLocation = $this->loadShippingLocation($options, $context, $salesChannel);
        }

        //customer group switched?
        if (array_key_exists(SalesChannelContextService::CUSTOMER_GROUP_ID, $options)) {
            $customerGroup = $this->customerGroupRepository->search(new Criteria([$options[SalesChannelContextService::CUSTOMER_GROUP_ID]]), $context)->get($options[SalesChannelContextService::CUSTOMER_GROUP_ID]);
        }

        //loads tax rules based on active customer group and delivery address
        //todo@dr load area based tax rules
        $criteria = new Criteria();
        $taxRules = $this->taxRepository->search($criteria, $context);

        //detect active payment method, first check if checkout defined other payment method, otherwise validate if customer logged in, at least use shop default
        $payment = $this->getPaymentMethod($options, $context, $salesChannel, $customer);

        //detect active delivery method, at first checkout scope, at least shop default method
        $delivery = $this->getShippingMethod($options, $context, $salesChannel);

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
            new TaxCollection($taxRules),
            $payment,
            $delivery,
            $shippingLocation,
            $customer,
            []
        );

        $salesChannelContext->setTaxState($this->taxDetector->getTaxState($salesChannelContext));

        return $salesChannelContext;
    }

    private function getPaymentMethod(array $options, Context $context, SalesChannelEntity $salesChannel, ?CustomerEntity $customer): PaymentMethodEntity
    {
        //payment switched in checkout?
        if (array_key_exists(SalesChannelContextService::PAYMENT_METHOD_ID, $options)) {
            return $this->paymentMethodRepository->search(new Criteria([$options[SalesChannelContextService::PAYMENT_METHOD_ID]]), $context)->get($options[SalesChannelContextService::PAYMENT_METHOD_ID]);
        }

        if (!$customer) {
            return $this->paymentMethodRepository->search(new Criteria([$salesChannel->getPaymentMethodId()]), $context)
                ->get($salesChannel->getPaymentMethodId());
        }

        if ($customer->getLastPaymentMethod()) {
            return $customer->getLastPaymentMethod();
        }

        return $customer->getDefaultPaymentMethod();
    }

    private function getShippingMethod(array $options, Context $context, SalesChannelEntity $salesChannel): ShippingMethodEntity
    {
        $id = $salesChannel->getShippingMethodId();
        if (array_key_exists(SalesChannelContextService::SHIPPING_METHOD_ID, $options)) {
            $id = $options[SalesChannelContextService::SHIPPING_METHOD_ID];
        }

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('priceRules');

        return $this->shippingMethodRepository->search($criteria, $context)->get($id);
    }

    private function getContext(string $salesChannelId, array $session): Context
    {
        $sql = '
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
        $languageIds = $data['sales_channel_language_ids'] ? explode(',', $data['sales_channel_language_ids']) : null;
        $languageIds = array_keys(array_flip($languageIds));

        //check which language should be used in the current request (request header set, or context already contains a language - stored in `sales_channel_api_context`)
        $defaultLanguageId = Uuid::fromBytesToHex($data['sales_channel_default_language_id']);

        $languageChain = $this->buildLanguageChain($session, $defaultLanguageId, $languageIds);

        return new Context(
            $origin,
            [],
            Uuid::fromBytesToHex($data['sales_channel_currency_id']),
            $languageChain,
            Defaults::LIVE_VERSION,
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

        /** @var CustomerEntity|null $customer */
        $customer = $this->customerRepository->search(new Criteria([$customerId]), $context)->get($customerId);

        if (!$customer) {
            return null;
        }

        $billingAddressId = $options[SalesChannelContextService::BILLING_ADDRESS_ID] ?? null;
        $shippingAddressId = $options[SalesChannelContextService::SHIPPING_ADDRESS_ID] ?? null;

        $addressIds = [];
        if (array_key_exists(SalesChannelContextService::BILLING_ADDRESS_ID, $options)) {
            $addressIds[] = $options[SalesChannelContextService::BILLING_ADDRESS_ID];
        }

        if (array_key_exists(SalesChannelContextService::SHIPPING_ADDRESS_ID, $options)) {
            $addressIds[] = $options[SalesChannelContextService::SHIPPING_ADDRESS_ID];
        }

        if (empty($addressIds)) {
            return $customer;
        }

        $addresses = $this->addressRepository->search(new Criteria($addressIds), $context);

        //billing address changed within checkout?
        if ($billingAddressId) {
            $customer->setActiveBillingAddress($addresses->get($billingAddressId));
        }

        //shipping address changed within checkout?
        if ($shippingAddressId) {
            $customer->setActiveShippingAddress($addresses->get($shippingAddressId));
        }

        return $customer;
    }

    private function loadShippingLocation(
        array $options,
        Context $context,
        SalesChannelEntity $salesChannel
    ): ShippingLocation {
        //allows to preview cart calculation for a specify state for not logged in customers
        if (array_key_exists(SalesChannelContextService::STATE_ID, $options)) {
            $state = $this->countryStateRepository->search(new Criteria([$options[SalesChannelContextService::STATE_ID]]), $context)
                ->get($options[SalesChannelContextService::STATE_ID]);

            $country = $this->countryRepository->search(new Criteria([$state->getCountryId()]), $context)
                ->get($state->getCountryId());

            return new ShippingLocation($country, $state, null);
        }

        $countryId = $salesChannel->getCountryId();
        if (array_key_exists(SalesChannelContextService::COUNTRY_ID, $options)) {
            $countryId = $options[SalesChannelContextService::COUNTRY_ID];
        }

        $country = $this->countryRepository->search(new Criteria([$countryId]), $context)
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
