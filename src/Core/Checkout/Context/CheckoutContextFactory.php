<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Context;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\Tax\TaxDetector;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Customer\CustomerStruct;
use Shopware\Core\Checkout\Payment\PaymentMethodStruct;
use Shopware\Core\Checkout\Shipping\ShippingMethodStruct;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\Read\ReadCriteria;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\ORM\Search\Criteria;
use Shopware\Core\Framework\SourceContext;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelStruct;
use Shopware\Core\System\Tax\TaxCollection;

class CheckoutContextFactory implements CheckoutContextFactoryInterface
{
    /**
     * @var RepositoryInterface
     */
    private $salesChannelRepository;

    /**
     * @var RepositoryInterface
     */
    private $currencyRepository;

    /**
     * @var RepositoryInterface
     */
    private $customerRepository;

    /**
     * @var RepositoryInterface
     */
    private $customerGroupRepository;

    /**
     * @var RepositoryInterface
     */
    private $countryRepository;

    /**
     * @var RepositoryInterface
     */
    private $taxRepository;

    /**
     * @var RepositoryInterface
     */
    private $addressRepository;

    /**
     * @var RepositoryInterface
     */
    private $paymentMethodRepository;

    /**
     * @var RepositoryInterface
     */
    private $shippingMethodRepository;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var RepositoryInterface
     */
    private $countryStateRepository;

    /**
     * @var RepositoryInterface
     */
    private $languageRepository;

    /**
     * @var TaxDetector
     */
    private $taxDetector;

    public function __construct(
        RepositoryInterface $salesChannelRepository,
        RepositoryInterface $currencyRepository,
        RepositoryInterface $customerRepository,
        RepositoryInterface $customerGroupRepository,
        RepositoryInterface $countryRepository,
        RepositoryInterface $taxRepository,
        RepositoryInterface $addressRepository,
        RepositoryInterface $paymentMethodRepository,
        RepositoryInterface $shippingMethodRepository,
        Connection $connection,
        RepositoryInterface $countryStateRepository,
        RepositoryInterface $languageRepository,
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
        $this->languageRepository = $languageRepository;
        $this->taxDetector = $taxDetector;
    }

    public function create(
        string $tenantId,
        string $token,
        string $salesChannelId,
        array $options = []
    ): CheckoutContext {
        $context = $this->getContext($salesChannelId, $tenantId);

        $salesChannel = $this->salesChannelRepository->read(new ReadCriteria([$context->getSourceContext()->getSalesChannelId()]), $context)
            ->get($context->getSourceContext()->getSalesChannelId());

        if (!$salesChannel) {
            throw new \RuntimeException(sprintf('Sales channel with id %s not found or not valid!', $context->getSourceContext()->getSalesChannelId()));
        }

        //load active currency, fallback to shop currency
        $currency = $salesChannel->getCurrency();
        if (array_key_exists(CheckoutContextService::CURRENCY_ID, $options)) {
            $currency = $this->currencyRepository->read(new ReadCriteria([$options[CheckoutContextService::CURRENCY_ID]]), $context)->get($options[CheckoutContextService::CURRENCY_ID]);
        }

        $language = $salesChannel->getLanguage();
        if (array_key_exists(CheckoutContextService::LANGUAGE_ID, $options)) {
            $language = $this->languageRepository->read(new ReadCriteria([$options[CheckoutContextService::LANGUAGE_ID]]), $context)->get($options[CheckoutContextService::LANGUAGE_ID]);
        }

        $fallbackLanguage = null;
        if ($language->getParentId()) {
            $fallbackLanguage = $this->languageRepository->read(new ReadCriteria([$language->getParentId()]), $context)->get($language->getParentId());
        }

        //fallback customer group is hard coded to 'EK'
        $customerGroups = $this->customerGroupRepository->read(new ReadCriteria([Defaults::FALLBACK_CUSTOMER_GROUP]), $context);
        $fallbackGroup = $customerGroups->get(Defaults::FALLBACK_CUSTOMER_GROUP);
        $customerGroup = $customerGroups->get(Defaults::FALLBACK_CUSTOMER_GROUP);

        // customer
        $customer = null;
        if (array_key_exists(CheckoutContextService::CUSTOMER_ID, $options)) {
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
        if (array_key_exists(CheckoutContextService::CUSTOMER_GROUP_ID, $options)) {
            $customerGroup = $this->customerGroupRepository->read(new ReadCriteria([$options[CheckoutContextService::CUSTOMER_GROUP_ID]]), $context)->get($options[CheckoutContextService::CUSTOMER_GROUP_ID]);
        }

        //loads tax rules based on active customer group and delivery address
        //todo@dr load area based tax rules
        $criteria = new Criteria();
        $taxRules = $this->taxRepository->search($criteria, $context);

        //detect active payment method, first check if checkout defined other payment method, otherwise validate if customer logged in, at least use shop default
        $payment = $this->getPaymentMethod($options, $context, $salesChannel, $customer);

        //detect active delivery method, at first checkout scope, at least shop default method
        $delivery = $this->getShippingMethod($options, $context, $salesChannel);

        $context = new CheckoutContext(
            $tenantId,
            $token,
            $salesChannel,
            $language,
            $fallbackLanguage,
            $currency,
            $customerGroup,
            $fallbackGroup,
            new TaxCollection($taxRules->getElements()),
            $payment,
            $delivery,
            $shippingLocation,
            $customer,
            []
        );

        $context->setTaxState($this->taxDetector->getTaxState($context));

        return $context;
    }

    private function getPaymentMethod(array $options, Context $context, SalesChannelStruct $salesChannel, ?CustomerStruct $customer): PaymentMethodStruct
    {
        //payment switched in checkout?
        if (array_key_exists(CheckoutContextService::PAYMENT_METHOD_ID, $options)) {
            return $this->paymentMethodRepository->read(new ReadCriteria([$options[CheckoutContextService::PAYMENT_METHOD_ID]]), $context)->get($options[CheckoutContextService::PAYMENT_METHOD_ID]);
        }

        //customer has a last payment method from previous order?
        if ($customer && $customer->getLastPaymentMethod()) {
            return $customer->getLastPaymentMethod();
        }

        //customer selected a default payment method in registration
        if ($customer && $customer->getDefaultPaymentMethod()) {
            return $customer->getDefaultPaymentMethod();
        }

        return $this->paymentMethodRepository->read(new ReadCriteria([$salesChannel->getPaymentMethodId()]), $context)
            ->get($salesChannel->getPaymentMethodId());
    }

    private function getShippingMethod(array $options, Context $context, SalesChannelStruct $salesChannel): ShippingMethodStruct
    {
        $id = $salesChannel->getShippingMethodId();
        if (array_key_exists(CheckoutContextService::SHIPPING_METHOD_ID, $options)) {
            $id = $options[CheckoutContextService::SHIPPING_METHOD_ID];
        }

        return $this->shippingMethodRepository->read(new ReadCriteria([$id]), $context)->get($id);
    }

    private function getContext(string $salesChannelId, string $tenantId): Context
    {
        $sql = '
        SELECT 
          sales_channel.id as sales_channel_id, 
          sales_channel.language_id as sales_channel_language_id,
          sales_channel.currency_id as sales_channel_currency_id,
          currency.factor as sales_channel_currency_factor,
          language.parent_id as sales_channel_language_parent_id,
          GROUP_CONCAT(HEX(sales_channel_catalog.catalog_id)) as sales_channel_catalog_ids
        FROM sales_channel
        INNER JOIN currency ON sales_channel.currency_id = currency.id
        INNER JOIN language ON sales_channel.language_id = language.id
        LEFT JOIN sales_channel_catalog ON sales_channel.id = sales_channel_catalog.sales_channel_id
          AND sales_channel.tenant_id = sales_channel_catalog.sales_channel_tenant_id
        WHERE sales_channel.id = :id AND sales_channel.tenant_id = :tenant_id
        GROUP BY sales_channel.id, sales_channel.language_id, sales_channel.currency_id, currency.factor, language.parent_id';

        $data = $this->connection->fetchAssoc($sql, [
            'id' => Uuid::fromHexToBytes($salesChannelId),
            'tenant_id' => Uuid::fromHexToBytes($tenantId),
        ]);

        $sourceContext = new SourceContext(SourceContext::ORIGIN_STOREFRONT_API);
        $sourceContext->setSalesChannelId($salesChannelId);

        $salesChannelCatalogIds = $data['sales_channel_catalog_ids'] ? explode(',', $data['sales_channel_catalog_ids']) : null;

        return new Context(
            $tenantId,
            $sourceContext,
            $salesChannelCatalogIds,
            [],
            Uuid::fromBytesToHex($data['sales_channel_currency_id']),
            Uuid::fromBytesToHex($data['sales_channel_language_id']),
            $data['sales_channel_language_parent_id'] ? Uuid::fromBytesToHex($data['sales_channel_language_parent_id']) : null,
            Defaults::LIVE_VERSION,
            (float) $data['sales_channel_currency_factor']
        );
    }

    private function loadCustomer(array $options, Context $context): ?CustomerStruct
    {
        $customerId = $options[CheckoutContextService::CUSTOMER_ID];

        /** @var CustomerStruct $customer */
        $customer = $this->customerRepository->read(new ReadCriteria([$customerId]), $context)->get($customerId);

        if (!$customer) {
            return null;
        }

        $billingAddressId = $options[CheckoutContextService::BILLING_ADDRESS_ID] ?? null;
        $shippingAddressId = $options[CheckoutContextService::SHIPPING_ADDRESS_ID] ?? null;

        $addressIds = [];
        if (array_key_exists(CheckoutContextService::BILLING_ADDRESS_ID, $options)) {
            $addressIds[] = $options[CheckoutContextService::BILLING_ADDRESS_ID];
        }

        if (array_key_exists(CheckoutContextService::SHIPPING_ADDRESS_ID, $options)) {
            $addressIds[] = $options[CheckoutContextService::SHIPPING_ADDRESS_ID];
        }

        if (empty($addressIds)) {
            return $customer;
        }

        $addresses = $this->addressRepository->read(new ReadCriteria($addressIds), $context);

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
        SalesChannelStruct $salesChannel
    ): ShippingLocation {
        //allows to preview cart calculation for a specify state for not logged in customers
        if (array_key_exists(CheckoutContextService::STATE_ID, $options)) {
            $state = $this->countryStateRepository->read(new ReadCriteria([$options[CheckoutContextService::STATE_ID]]), $context)
                ->get($options[CheckoutContextService::STATE_ID]);

            $country = $this->countryRepository->read(new ReadCriteria([$state->getCountryId()]), $context)
                ->get($state->getCountryId());

            return new ShippingLocation($country, $state, null);
        }

        $countryId = $salesChannel->getCountryId();
        if (array_key_exists(CheckoutContextService::COUNTRY_ID, $options)) {
            $countryId = $options[CheckoutContextService::COUNTRY_ID];
        }

        $country = $this->countryRepository->read(new ReadCriteria([$countryId]), $context)
            ->get($countryId);

        return ShippingLocation::createFromCountry($country);
    }
}
