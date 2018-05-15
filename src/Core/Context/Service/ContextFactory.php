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

namespace Shopware\Context\Service;

use Doctrine\DBAL\Connection;
use Shopware\Application\Application\Repository\ApplicationRepository;
use Shopware\Application\Application\Struct\ApplicationBasicStruct;
use Shopware\System\Country\Repository\CountryRepository;
use Shopware\System\Country\Repository\CountryStateRepository;
use Shopware\System\Currency\Repository\CurrencyRepository;
use Shopware\Checkout\Customer\Repository\CustomerAddressRepository;
use Shopware\Checkout\Customer\Repository\CustomerGroupRepository;
use Shopware\Checkout\Customer\Repository\CustomerRepository;
use Shopware\Checkout\Customer\Struct\CustomerBasicStruct;
use Shopware\Framework\ORM\Search\Criteria;
use Shopware\Application\Language\Repository\LanguageRepository;
use Shopware\Checkout\Payment\Repository\PaymentMethodRepository;
use Shopware\Checkout\Payment\Struct\PaymentMethodBasicStruct;
use Shopware\Checkout\Shipping\Repository\ShippingMethodRepository;
use Shopware\Checkout\Shipping\Struct\ShippingMethodBasicStruct;
use Shopware\System\Tax\Collection\TaxBasicCollection;
use Shopware\System\Tax\Repository\TaxRepository;
use Shopware\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Checkout\Cart\Tax\TaxDetector;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Context\Struct\StorefrontContext;
use Shopware\Defaults;
use Shopware\Framework\Struct\Uuid;
use Shopware\StorefrontApi\Context\StorefrontContextService;

class ContextFactory implements ContextFactoryInterface
{
    /**
     * @var ApplicationRepository
     */
    private $applicationRepository;

    /**
     * @var CurrencyRepository
     */
    private $currencyRepository;

    /**
     * @var CustomerRepository
     */
    private $customerRepository;

    /**
     * @var CustomerGroupRepository
     */
    private $customerGroupRepository;

    /**
     * @var CountryRepository
     */
    private $countryRepository;

    /**
     * @var TaxRepository
     */
    private $taxRepository;

    /**
     * @var CustomerAddressRepository
     */
    private $addressRepository;

    /**
     * @var PaymentMethodRepository
     */
    private $paymentMethodRepository;

    /**
     * @var ShippingMethodRepository
     */
    private $shippingMethodRepository;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var CountryStateRepository
     */
    private $countryStateRepository;

    /**
     * @var LanguageRepository
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
    ): StorefrontContext {
        $applicationContext = $this->getApplicationContext($applicationId, $tenantId);

        $application = $this->applicationRepository->readBasic([$applicationContext->getApplicationId()], $applicationContext)
            ->get($applicationContext->getApplicationId());

        if (!$application) {
            throw new \RuntimeException(sprintf('Application with id %s not found or not valid!', $applicationContext->getApplicationId()));
        }

        //load active currency, fallback to shop currency
        $currency = $application->getCurrency();
        if (array_key_exists(StorefrontContextService::CURRENCY_ID, $options)) {
            $currency = $this->currencyRepository->readBasic([$options[StorefrontContextService::CURRENCY_ID]], $applicationContext)->get($options[StorefrontContextService::CURRENCY_ID]);
        }

        $language = $application->getLanguage();
        if (array_key_exists(StorefrontContextService::LANGUAGE_ID, $options)) {
            $language = $this->languageRepository->readBasic([$options[StorefrontContextService::LANGUAGE_ID]], $applicationContext)->get($options[StorefrontContextService::LANGUAGE_ID]);
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
        if (array_key_exists(StorefrontContextService::CUSTOMER_ID, $options)) {
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
        if (array_key_exists(StorefrontContextService::CUSTOMER_GROUP_ID, $options)) {
            $customerGroup = $this->customerGroupRepository->readBasic([$options[StorefrontContextService::CUSTOMER_GROUP_ID]], $applicationContext)->get($options[StorefrontContextService::CUSTOMER_GROUP_ID]);
        }

        //loads tax rules based on active customer group and delivery address
        //todo@dr load area based tax rules
        $criteria = new Criteria();
        $taxRules = $this->taxRepository->search($criteria, $applicationContext);

        //detect active payment method, first check if checkout defined other payment method, otherwise validate if customer logged in, at least use shop default
        $payment = $this->getPaymentMethod($options, $applicationContext, $application, $customer);

        //detect active delivery method, at first checkout scope, at least shop default method
        $delivery = $this->getShippingMethod($options, $applicationContext, $application);

        $context = new StorefrontContext(
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

    private function getPaymentMethod(array $options, ApplicationContext $context, ApplicationBasicStruct $application, ?CustomerBasicStruct $customer): PaymentMethodBasicStruct
    {
        //payment switched in checkout?
        if (array_key_exists(StorefrontContextService::PAYMENT_METHOD_ID, $options)) {
            return $this->paymentMethodRepository->readBasic([$options[StorefrontContextService::PAYMENT_METHOD_ID]], $context)->get($options[StorefrontContextService::PAYMENT_METHOD_ID]);
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

    private function getShippingMethod(array $options, ApplicationContext $context, ApplicationBasicStruct $application): ShippingMethodBasicStruct
    {
        $id = $application->getShippingMethodId();
        if (array_key_exists(StorefrontContextService::SHIPPING_METHOD_ID, $options)) {
            $id = $options[StorefrontContextService::SHIPPING_METHOD_ID];
        }

        return $this->shippingMethodRepository->readBasic([$id], $context)->get($id);
    }

    private function getApplicationContext(string $applicationId, string $tenantId): ApplicationContext
    {
        $query = $this->connection->createQueryBuilder();
        $query->select([
            'application.id as shop_id',
            'application.language_id as shop_language_id',
            'application.currency_id as shop_currency_id',
            'application.catalog_ids as shop_catalog_ids',
            'currency.factor as shop_currency_factor',
            'language.parent_id as shop_language_parent_id',
        ]);
        $query->from('application', 'application');
        $query->innerJoin('application', 'currency', 'currency', 'application.currency_id = currency.id');
        $query->innerJoin('application', 'language', 'language', 'application.language_id = language.id');
        $query->andWhere('application.id = :id');
        $query->andWhere('application.tenant_id = :tenant');
        $query->setParameter('id', Uuid::fromHexToBytes($applicationId));
        $query->setParameter('tenant', Uuid::fromHexToBytes($tenantId));

        $data = $query->execute()->fetch(\PDO::FETCH_ASSOC);

        return new ApplicationContext(
            $tenantId,
            Uuid::fromBytesToHex($data['shop_id']),
            json_decode($data['shop_catalog_ids'], true),
            [],
            Uuid::fromBytesToHex($data['shop_currency_id']),
            Uuid::fromBytesToHex($data['shop_language_id']),
            $data['shop_language_parent_id'] ? Uuid::fromBytesToHex($data['shop_language_parent_id']) : null,
            Defaults::LIVE_VERSION,
            (float) $data['shop_currency_factor']
        );
    }

    private function loadCustomer(array $options, ApplicationContext $applicationContext): ?CustomerBasicStruct
    {
        $customerId = $options[StorefrontContextService::CUSTOMER_ID];
        $customer = $this->customerRepository->readBasic([$customerId], $applicationContext)->get($customerId);

        if (!$customer) {
            return $customer;
        }

        if (array_key_exists(StorefrontContextService::BILLING_ADDRESS_ID, $options) === false && array_key_exists(StorefrontContextService::SHIPPING_ADDRESS_ID, $options) === false) {
            return $customer;
        }

        $billingAddressId = $options[StorefrontContextService::BILLING_ADDRESS_ID];
        $shippingAddressId = $options[StorefrontContextService::SHIPPING_ADDRESS_ID];

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
        ApplicationContext $applicationContext,
        ApplicationBasicStruct $application
    ): ShippingLocation {
        //allows to preview cart calculation for a specify state for not logged in customers
        if (array_key_exists(StorefrontContextService::STATE_ID, $options)) {
            $state = $this->countryStateRepository->readBasic([$options[StorefrontContextService::STATE_ID]], $applicationContext)
                ->get($options[StorefrontContextService::STATE_ID]);

            $country = $this->countryRepository->readBasic([$state->getCountryId()], $applicationContext)
                ->get($state->getCountryId());

            return new ShippingLocation($country, $state, null);
        }

        $countryId = $application->getCountryId();
        if (array_key_exists(StorefrontContextService::COUNTRY_ID, $options)) {
            $countryId = $options[StorefrontContextService::COUNTRY_ID];
        }

        $country = $this->countryRepository->readBasic([$countryId], $applicationContext)
            ->get($countryId);

        return ShippingLocation::createFromCountry($country);
    }
}
