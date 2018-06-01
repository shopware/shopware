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

namespace Shopware\Checkout\Customer\Util;

use Doctrine\DBAL\Connection;
use Shopware\Application\Application\ApplicationRepository;
use Shopware\Application\Application\Struct\ApplicationBasicStruct;
use Shopware\Framework\Context;
use Shopware\Checkout\CustomerContext;
use Shopware\Application\Language\LanguageRepository;
use Shopware\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Checkout\Cart\Tax\TaxDetector;
use Shopware\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressRepository;
use Shopware\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupRepository;
use Shopware\Checkout\Customer\CustomerRepository;
use Shopware\Checkout\Customer\Struct\CustomerBasicStruct;
use Shopware\Checkout\Payment\PaymentMethodRepository;
use Shopware\Checkout\Payment\Struct\PaymentMethodBasicStruct;
use Shopware\Checkout\Shipping\ShippingMethodRepository;
use Shopware\Checkout\Shipping\Struct\ShippingMethodBasicStruct;
use Shopware\Defaults;
use Shopware\Framework\ORM\Search\Criteria;
use Shopware\Framework\Struct\Uuid;
use Shopware\System\Country\Aggregate\CountryState\CountryStateRepository;
use Shopware\System\Country\CountryRepository;
use Shopware\System\Currency\CurrencyRepository;
use Shopware\System\Tax\Collection\TaxBasicCollection;
use Shopware\System\Tax\TaxRepository;

class CustomerContextFactory implements CustomerContextFactoryInterface
{
    /**
     * @var \Shopware\Application\Application\ApplicationRepository
     */
    private $applicationRepository;

    /**
     * @var CurrencyRepository
     */
    private $currencyRepository;

    /**
     * @var \Shopware\Checkout\Customer\CustomerRepository
     */
    private $customerRepository;

    /**
     * @var CustomerGroupRepository
     */
    private $customerGroupRepository;

    /**
     * @var \Shopware\System\Country\CountryRepository
     */
    private $countryRepository;

    /**
     * @var \Shopware\System\Tax\TaxRepository
     */
    private $taxRepository;

    /**
     * @var CustomerAddressRepository
     */
    private $addressRepository;

    /**
     * @var \Shopware\Checkout\Payment\PaymentMethodRepository
     */
    private $paymentMethodRepository;

    /**
     * @var \Shopware\Checkout\Shipping\ShippingMethodRepository
     */
    private $shippingMethodRepository;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var \Shopware\System\Country\Aggregate\CountryState\CountryStateRepository
     */
    private $countryStateRepository;

    /**
     * @var \Shopware\Application\Language\LanguageRepository
     */
    private $languageRepository;

    /**
     * @var TaxDetector
     */
    private $taxDetector;

    public function __construct(
        ApplicationRepository $applicationRepository,
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
        $this->applicationRepository = $applicationRepository;
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
        string $applicationId,
        array $options = []
    ): CustomerContext {
        $applicationContext = $this->getApplicationContext($applicationId, $tenantId);

        $application = $this->applicationRepository->readBasic([$applicationContext->getApplicationId()], $applicationContext)
            ->get($applicationContext->getApplicationId());

        if (!$application) {
            throw new \RuntimeException(sprintf('Application with id %s not found or not valid!', $applicationContext->getApplicationId()));
        }

        //load active currency, fallback to shop currency
        $currency = $application->getCurrency();
        if (array_key_exists(CustomerContextService::CURRENCY_ID, $options)) {
            $currency = $this->currencyRepository->readBasic([$options[CustomerContextService::CURRENCY_ID]], $applicationContext)->get($options[CustomerContextService::CURRENCY_ID]);
        }

        $language = $application->getLanguage();
        if (array_key_exists(CustomerContextService::LANGUAGE_ID, $options)) {
            $language = $this->languageRepository->readBasic([$options[CustomerContextService::LANGUAGE_ID]], $applicationContext)->get($options[CustomerContextService::LANGUAGE_ID]);
        }

        $fallbackLanguage = null;
        if ($language->getParentId()) {
            $language = $this->languageRepository->readBasic([$language->getParentId()], $applicationContext)->get($language->getParentId());
        }

        //fallback customer group is hard coded to 'EK'
        $customerGroups = $this->customerGroupRepository->readBasic([Defaults::FALLBACK_CUSTOMER_GROUP], $applicationContext);
        $fallbackGroup = $customerGroups->get(Defaults::FALLBACK_CUSTOMER_GROUP);
        $customerGroup = $customerGroups->get(Defaults::FALLBACK_CUSTOMER_GROUP);

        // customer
        $customer = null;
        if (array_key_exists(CustomerContextService::CUSTOMER_ID, $options)) {
            //load logged in customer and set active addresses
            $customer = $this->loadCustomer($options, $applicationContext);

            if ($customer) {
                $shippingLocation = ShippingLocation::createFromAddress($customer->getActiveShippingAddress());
                $customerGroup = $customer->getGroup();
            }
        } else {
            //load not logged in customer with default shop configuration or with provided checkout scopes
            $shippingLocation = $this->loadShippingLocation($options, $applicationContext, $application);
        }

        //customer group switched?
        if (array_key_exists(CustomerContextService::CUSTOMER_GROUP_ID, $options)) {
            $customerGroup = $this->customerGroupRepository->readBasic([$options[CustomerContextService::CUSTOMER_GROUP_ID]], $applicationContext)->get($options[CustomerContextService::CUSTOMER_GROUP_ID]);
        }

        //loads tax rules based on active customer group and delivery address
        //todo@dr load area based tax rules
        $criteria = new Criteria();
        $taxRules = $this->taxRepository->search($criteria, $applicationContext);

        //detect active payment method, first check if checkout defined other payment method, otherwise validate if customer logged in, at least use shop default
        $payment = $this->getPaymentMethod($options, $applicationContext, $application, $customer);

        //detect active delivery method, at first checkout scope, at least shop default method
        $delivery = $this->getShippingMethod($options, $applicationContext, $application);

        $context = new CustomerContext(
            $tenantId,
            $token,
            $application,
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

    private function getPaymentMethod(array $options, Context $context, ApplicationBasicStruct $application, ?CustomerBasicStruct $customer): PaymentMethodBasicStruct
    {
        //payment switched in checkout?
        if (array_key_exists(CustomerContextService::PAYMENT_METHOD_ID, $options)) {
            return $this->paymentMethodRepository->readBasic([$options[CustomerContextService::PAYMENT_METHOD_ID]], $context)->get($options[CustomerContextService::PAYMENT_METHOD_ID]);
        }

        //customer has a last payment method from previous order?
        if ($customer && $customer->getLastPaymentMethod()) {
            return $customer->getLastPaymentMethod();
        }

        //customer selected a default payment method in registration
        if ($customer && $customer->getDefaultPaymentMethod()) {
            return $customer->getDefaultPaymentMethod();
        }

        return $this->paymentMethodRepository->readBasic([$application->getPaymentMethodId()], $context)
            ->get($application->getPaymentMethodId());
    }

    private function getShippingMethod(array $options, Context $context, ApplicationBasicStruct $application): ShippingMethodBasicStruct
    {
        $id = $application->getShippingMethodId();
        if (array_key_exists(CustomerContextService::SHIPPING_METHOD_ID, $options)) {
            $id = $options[CustomerContextService::SHIPPING_METHOD_ID];
        }

        return $this->shippingMethodRepository->readBasic([$id], $context)->get($id);
    }

    private function getApplicationContext(string $applicationId, string $tenantId): Context
    {
        $query = $this->connection->createQueryBuilder();
        $query->select([
            'application.id as application_id',
            'application.language_id as application_language_id',
            'application.currency_id as application_currency_id',
            'application.catalog_ids as application_catalog_ids',
            'currency.factor as application_currency_factor',
            'language.parent_id as application_language_parent_id',
        ]);
        $query->from('application', 'application');
        $query->innerJoin('application', 'currency', 'currency', 'application.currency_id = currency.id');
        $query->innerJoin('application', 'language', 'language', 'application.language_id = language.id');
        $query->andWhere('application.id = :id');
        $query->andWhere('application.tenant_id = :tenant');
        $query->setParameter('id', Uuid::fromHexToBytes($applicationId));
        $query->setParameter('tenant', Uuid::fromHexToBytes($tenantId));

        $data = $query->execute()->fetch(\PDO::FETCH_ASSOC);

        return new Context(
            $tenantId,
            Uuid::fromBytesToHex($data['application_id']),
            json_decode($data['application_catalog_ids'], true),
            [],
            Uuid::fromBytesToHex($data['application_currency_id']),
            Uuid::fromBytesToHex($data['application_language_id']),
            $data['application_language_parent_id'] ? Uuid::fromBytesToHex($data['application_language_parent_id']) : null,
            Defaults::LIVE_VERSION,
            (float) $data['application_currency_factor']
        );
    }

    private function loadCustomer(array $options, Context $applicationContext): ?CustomerBasicStruct
    {
        $customerId = $options[CustomerContextService::CUSTOMER_ID];
        $customer = $this->customerRepository->readBasic([$customerId], $applicationContext)->get($customerId);

        if (!$customer) {
            return $customer;
        }

        if (array_key_exists(CustomerContextService::BILLING_ADDRESS_ID, $options) === false && array_key_exists(CustomerContextService::SHIPPING_ADDRESS_ID, $options) === false) {
            return $customer;
        }

        $billingAddressId = $options[CustomerContextService::BILLING_ADDRESS_ID];
        $shippingAddressId = $options[CustomerContextService::SHIPPING_ADDRESS_ID];

        $addresses = $this->addressRepository->readBasic([$billingAddressId, $shippingAddressId], $applicationContext);

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
        Context $applicationContext,
        ApplicationBasicStruct $application
    ): ShippingLocation {
        //allows to preview cart calculation for a specify state for not logged in customers
        if (array_key_exists(CustomerContextService::STATE_ID, $options)) {
            $state = $this->countryStateRepository->readBasic([$options[CustomerContextService::STATE_ID]], $applicationContext)
                ->get($options[CustomerContextService::STATE_ID]);

            $country = $this->countryRepository->readBasic([$state->getCountryId()], $applicationContext)
                ->get($state->getCountryId());

            return new ShippingLocation($country, $state, null);
        }

        $countryId = $application->getCountryId();
        if (array_key_exists(CustomerContextService::COUNTRY_ID, $options)) {
            $countryId = $options[CustomerContextService::COUNTRY_ID];
        }

        $country = $this->countryRepository->readBasic([$countryId], $applicationContext)
            ->get($countryId);

        return ShippingLocation::createFromCountry($country);
    }
}
