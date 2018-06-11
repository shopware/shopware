<?php declare(strict_types=1);
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\Core\Checkout\Context;

use Doctrine\DBAL\Connection;
use Shopware\Core\System\Touchpoint\TouchpointRepository;
use Shopware\Core\System\Touchpoint\Struct\TouchpointBasicStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\System\Language\LanguageRepository;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\Tax\TaxDetector;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressRepository;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupRepository;
use Shopware\Core\Checkout\Customer\CustomerRepository;
use Shopware\Core\Checkout\Customer\Struct\CustomerBasicStruct;
use Shopware\Core\Checkout\Payment\PaymentMethodRepository;
use Shopware\Core\Checkout\Payment\Struct\PaymentMethodBasicStruct;
use Shopware\Core\Checkout\Shipping\ShippingMethodRepository;
use Shopware\Core\Checkout\Shipping\Struct\ShippingMethodBasicStruct;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\ORM\Search\Criteria;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateRepository;
use Shopware\Core\System\Country\CountryRepository;
use Shopware\Core\System\Currency\CurrencyRepository;
use Shopware\Core\System\Tax\Collection\TaxBasicCollection;
use Shopware\Core\System\Tax\TaxRepository;

class CheckoutContextFactory implements CheckoutContextFactoryInterface
{
    /**
     * @var \Shopware\Core\System\Touchpoint\TouchpointRepository
     */
    private $touchpointRepository;

    /**
     * @var CurrencyRepository
     */
    private $currencyRepository;

    /**
     * @var \Shopware\Core\Checkout\Customer\CustomerRepository
     */
    private $customerRepository;

    /**
     * @var CustomerGroupRepository
     */
    private $customerGroupRepository;

    /**
     * @var \Shopware\Core\System\Country\CountryRepository
     */
    private $countryRepository;

    /**
     * @var \Shopware\Core\System\Tax\TaxRepository
     */
    private $taxRepository;

    /**
     * @var CustomerAddressRepository
     */
    private $addressRepository;

    /**
     * @var \Shopware\Core\Checkout\Payment\PaymentMethodRepository
     */
    private $paymentMethodRepository;

    /**
     * @var \Shopware\Core\Checkout\Shipping\ShippingMethodRepository
     */
    private $shippingMethodRepository;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var \Shopware\Core\System\Country\Aggregate\CountryState\CountryStateRepository
     */
    private $countryStateRepository;

    /**
     * @var \Shopware\Core\System\Language\LanguageRepository
     */
    private $languageRepository;

    /**
     * @var TaxDetector
     */
    private $taxDetector;

    public function __construct(
        TouchpointRepository $touchpointRepository,
        CurrencyRepository $currencyRepository,
        CustomerRepository $customerRepository,
        CustomerGroupRepository $customerGroupRepository,
        CountryRepository $countryRepository,
        TaxRepository $taxRepository,
        CustomerAddressRepository $addressRepository,
        PaymentMethodRepository $paymentMethodRepository,
        ShippingMethodRepository $shippingMethodRepository,
        Connection $connection,
        CountryStateRepository $countryStateRepository,
        LanguageRepository $languageRepository,
        TaxDetector $taxDetector
    ) {
        $this->touchpointRepository = $touchpointRepository;
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
        string $touchpointId,
        array $options = []
    ): CheckoutContext {
        $context = $this->getContext($touchpointId, $tenantId);

        $touchpoint = $this->touchpointRepository->readBasic([$context->getTouchpointId()], $context)
            ->get($context->getTouchpointId());

        if (!$touchpoint) {
            throw new \RuntimeException(sprintf('Touchpoint with id %s not found or not valid!', $context->getTouchpointId()));
        }

        //load active currency, fallback to shop currency
        $currency = $touchpoint->getCurrency();
        if (array_key_exists(CheckoutContextService::CURRENCY_ID, $options)) {
            $currency = $this->currencyRepository->readBasic([$options[CheckoutContextService::CURRENCY_ID]], $context)->get($options[CheckoutContextService::CURRENCY_ID]);
        }

        $language = $touchpoint->getLanguage();
        if (array_key_exists(CheckoutContextService::LANGUAGE_ID, $options)) {
            $language = $this->languageRepository->readBasic([$options[CheckoutContextService::LANGUAGE_ID]], $context)->get($options[CheckoutContextService::LANGUAGE_ID]);
        }

        $fallbackLanguage = null;
        if ($language->getParentId()) {
            $language = $this->languageRepository->readBasic([$language->getParentId()], $context)->get($language->getParentId());
        }

        //fallback customer group is hard coded to 'EK'
        $customerGroups = $this->customerGroupRepository->readBasic([Defaults::FALLBACK_CUSTOMER_GROUP], $context);
        $fallbackGroup = $customerGroups->get(Defaults::FALLBACK_CUSTOMER_GROUP);
        $customerGroup = $customerGroups->get(Defaults::FALLBACK_CUSTOMER_GROUP);

        // customer
        $customer = null;
        if (array_key_exists(CheckoutContextService::CUSTOMER_ID, $options)) {
            //load logged in customer and set active addresses
            $customer = $this->loadCustomer($options, $context);

            if ($customer) {
                $shippingLocation = ShippingLocation::createFromAddress($customer->getActiveShippingAddress());
                $customerGroup = $customer->getGroup();
            }
        } else {
            //load not logged in customer with default shop configuration or with provided checkout scopes
            $shippingLocation = $this->loadShippingLocation($options, $context, $touchpoint);
        }

        //customer group switched?
        if (array_key_exists(CheckoutContextService::CUSTOMER_GROUP_ID, $options)) {
            $customerGroup = $this->customerGroupRepository->readBasic([$options[CheckoutContextService::CUSTOMER_GROUP_ID]], $context)->get($options[CheckoutContextService::CUSTOMER_GROUP_ID]);
        }

        //loads tax rules based on active customer group and delivery address
        //todo@dr load area based tax rules
        $criteria = new Criteria();
        $taxRules = $this->taxRepository->search($criteria, $context);

        //detect active payment method, first check if checkout defined other payment method, otherwise validate if customer logged in, at least use shop default
        $payment = $this->getPaymentMethod($options, $context, $touchpoint, $customer);

        //detect active delivery method, at first checkout scope, at least shop default method
        $delivery = $this->getShippingMethod($options, $context, $touchpoint);

        $context = new CheckoutContext(
            $tenantId,
            $token,
            $touchpoint,
            $language,
            $fallbackLanguage,
            $currency,
            $customerGroup,
            $fallbackGroup,
            new TaxBasicCollection($taxRules->getElements()),
            $payment,
            $delivery,
            $shippingLocation,
            $customer,
            []
        );

        $context->setTaxState($this->taxDetector->getTaxState($context));

        return $context;
    }

    private function getPaymentMethod(array $options, Context $context, TouchpointBasicStruct $touchpoint, ?CustomerBasicStruct $customer): PaymentMethodBasicStruct
    {
        //payment switched in checkout?
        if (array_key_exists(CheckoutContextService::PAYMENT_METHOD_ID, $options)) {
            return $this->paymentMethodRepository->readBasic([$options[CheckoutContextService::PAYMENT_METHOD_ID]], $context)->get($options[CheckoutContextService::PAYMENT_METHOD_ID]);
        }

        //customer has a last payment method from previous order?
        if ($customer && $customer->getLastPaymentMethod()) {
            return $customer->getLastPaymentMethod();
        }

        //customer selected a default payment method in registration
        if ($customer && $customer->getDefaultPaymentMethod()) {
            return $customer->getDefaultPaymentMethod();
        }

        return $this->paymentMethodRepository->readBasic([$touchpoint->getPaymentMethodId()], $context)
            ->get($touchpoint->getPaymentMethodId());
    }

    private function getShippingMethod(array $options, Context $context, TouchpointBasicStruct $touchpoint): ShippingMethodBasicStruct
    {
        $id = $touchpoint->getShippingMethodId();
        if (array_key_exists(CheckoutContextService::SHIPPING_METHOD_ID, $options)) {
            $id = $options[CheckoutContextService::SHIPPING_METHOD_ID];
        }

        return $this->shippingMethodRepository->readBasic([$id], $context)->get($id);
    }

    private function getContext(string $touchpointId, string $tenantId): Context
    {
        $query = $this->connection->createQueryBuilder();
        $query->select([
            'touchpoint.id as touchpoint_id',
            'touchpoint.language_id as touchpoint_language_id',
            'touchpoint.currency_id as touchpoint_currency_id',
            'touchpoint.catalog_ids as touchpoint_catalog_ids',
            'currency.factor as touchpoint_currency_factor',
            'language.parent_id as touchpoint_language_parent_id',
        ]);
        $query->from('touchpoint', 'touchpoint');
        $query->innerJoin('touchpoint', 'currency', 'currency', 'touchpoint.currency_id = currency.id');
        $query->innerJoin('touchpoint', 'language', 'language', 'touchpoint.language_id = language.id');
        $query->andWhere('touchpoint.id = :id');
        $query->andWhere('touchpoint.tenant_id = :tenant');
        $query->setParameter('id', Uuid::fromHexToBytes($touchpointId));
        $query->setParameter('tenant', Uuid::fromHexToBytes($tenantId));

        $data = $query->execute()->fetch(\PDO::FETCH_ASSOC);

        return new Context(
            $tenantId,
            Uuid::fromBytesToHex($data['touchpoint_id']),
            json_decode($data['touchpoint_catalog_ids'], true),
            [],
            Uuid::fromBytesToHex($data['touchpoint_currency_id']),
            Uuid::fromBytesToHex($data['touchpoint_language_id']),
            $data['touchpoint_language_parent_id'] ? Uuid::fromBytesToHex($data['touchpoint_language_parent_id']) : null,
            Defaults::LIVE_VERSION,
            (float) $data['touchpoint_currency_factor']
        );
    }

    private function loadCustomer(array $options, Context $context): ?CustomerBasicStruct
    {
        $customerId = $options[CheckoutContextService::CUSTOMER_ID];
        $customer = $this->customerRepository->readBasic([$customerId], $context)->get($customerId);

        if (!$customer) {
            return $customer;
        }

        if (array_key_exists(CheckoutContextService::BILLING_ADDRESS_ID, $options) === false && array_key_exists(CheckoutContextService::SHIPPING_ADDRESS_ID, $options) === false) {
            return $customer;
        }

        $billingAddressId = $options[CheckoutContextService::BILLING_ADDRESS_ID];
        $shippingAddressId = $options[CheckoutContextService::SHIPPING_ADDRESS_ID];

        $addresses = $this->addressRepository->readBasic([$billingAddressId, $shippingAddressId], $context);

        //billing address changed within checkout?
        if ($billingAddressId !== null) {
            $customer->setActiveBillingAddress($addresses->get($billingAddressId));
        }

        //shipping address changed within checkout?
        if ($shippingAddressId !== null) {
            $customer->setActiveShippingAddress($addresses->get($shippingAddressId));
        }

        return $customer;
    }

    private function loadShippingLocation(
        array $options,
        Context $context,
        TouchpointBasicStruct $touchpoint
    ): ShippingLocation {
        //allows to preview cart calculation for a specify state for not logged in customers
        if (array_key_exists(CheckoutContextService::STATE_ID, $options)) {
            $state = $this->countryStateRepository->readBasic([$options[CheckoutContextService::STATE_ID]], $context)
                ->get($options[CheckoutContextService::STATE_ID]);

            $country = $this->countryRepository->readBasic([$state->getCountryId()], $context)
                ->get($state->getCountryId());

            return new ShippingLocation($country, $state, null);
        }

        $countryId = $touchpoint->getCountryId();
        if (array_key_exists(CheckoutContextService::COUNTRY_ID, $options)) {
            $countryId = $options[CheckoutContextService::COUNTRY_ID];
        }

        $country = $this->countryRepository->readBasic([$countryId], $context)
            ->get($countryId);

        return ShippingLocation::createFromCountry($country);
    }
}
