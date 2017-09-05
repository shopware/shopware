<?php
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
use Shopware\AreaCountry\AreaCountryRepository;
use Shopware\AreaCountryState\AreaCountryStateRepository;
use Shopware\Cart\Delivery\ShippingLocation;
use Shopware\Context\Struct\CheckoutScope;
use Shopware\Context\Struct\CustomerScope;
use Shopware\Context\Struct\ShopContext;
use Shopware\Context\Struct\ShopScope;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Currency\CurrencyRepository;
use Shopware\Currency\Struct\Currency;
use Shopware\Currency\Struct\CurrencyBasicStruct;
use Shopware\Customer\CustomerRepository;
use Shopware\Customer\Struct\Customer;
use Shopware\Customer\Struct\CustomerBasicStruct;
use Shopware\CustomerAddress\CustomerAddressRepository;
use Shopware\CustomerGroup\CustomerGroupRepository;
use Shopware\PaymentMethod\PaymentMethodRepository;
use Shopware\PaymentMethod\Struct\PaymentMethod;
use Shopware\PaymentMethod\Struct\PaymentMethodBasicStruct;
use Shopware\PriceGroup\PriceGroupRepository;
use Shopware\PriceGroupDiscount\PriceGroupDiscountRepository;
use Shopware\Search\Condition\GroupKeyCondition;
use Shopware\Search\Criteria;
use Shopware\ShippingMethod\ShippingMethodRepository;
use Shopware\ShippingMethod\Struct\ShippingMethod;
use Shopware\ShippingMethod\Struct\ShippingMethodBasicStruct;
use Shopware\Shop\ShopRepository;
use Shopware\Shop\Struct\Shop;
use Shopware\Shop\Struct\ShopBasicStruct;
use Shopware\Shop\Struct\ShopDetailStruct;
use Shopware\Storefront\Context\StorefrontContextService;
use Shopware\Tax\TaxRepository;

class ContextFactory implements ContextFactoryInterface
{
    /**
     * @var ShopRepository
     */
    private $shopRepository;

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
     * @var AreaCountryRepository
     */
    private $countryRepository;

    /**
     * @var TaxRepository
     */
    private $taxRepository;

    /**
     * @var PriceGroupDiscountRepository
     */
    private $priceGroupDiscountRepository;

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
     * @var AreaCountryStateRepository
     */
    private $countryStateRepository;

    public function __construct(
        ShopRepository $shopRepository,
        CurrencyRepository $currencyRepository,
        CustomerRepository $customerRepository,
        CustomerGroupRepository $customerGroupRepository,
        AreaCountryRepository $countryRepository,
        TaxRepository $taxRepository,
        PriceGroupDiscountRepository $priceGroupDiscountRepository,
        CustomerAddressRepository $addressRepository,
        PaymentMethodRepository $paymentMethodRepository,
        ShippingMethodRepository $shippingMethodRepository,
        Connection $connection,
        AreaCountryStateRepository $countryStateRepository
    ) {
        $this->shopRepository = $shopRepository;
        $this->currencyRepository = $currencyRepository;
        $this->customerRepository = $customerRepository;
        $this->customerGroupRepository = $customerGroupRepository;
        $this->countryRepository = $countryRepository;
        $this->taxRepository = $taxRepository;
        $this->priceGroupDiscountRepository = $priceGroupDiscountRepository;
        $this->addressRepository = $addressRepository;
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->shippingMethodRepository = $shippingMethodRepository;
        $this->connection = $connection;
        $this->countryStateRepository = $countryStateRepository;
    }

    public function create(
        ShopScope $shopScope,
        CustomerScope $customerScope,
        CheckoutScope $checkoutScope
    ): ShopContext {
        $translationContext = $this->getTranslationContext($shopScope->getShopUuid());

        //select shop with all fallbacks
        /** @var ShopDetailStruct $shop */
        $shop = $this->shopRepository->readDetail([$shopScope->getShopUuid()], $translationContext)
            ->get($shopScope->getShopUuid());

        if (!$shop) {
            throw new \RuntimeException(sprintf('Shop with id %s not found or not valid!', $shopScope->getShopUuid()));
        }

        //load active currency, fallback to shop currency
        $currency = $this->getCurrency($shop, $shopScope->getCurrencyUuid(), $translationContext);
        
        //fallback customer group is hard coded to 'EK'
        $customerGroups = $this->customerGroupRepository->read(
            [StorefrontContextService::FALLBACK_CUSTOMER_GROUP],
            $translationContext
        );

        $fallbackGroup = $customerGroups->get(StorefrontContextService::FALLBACK_CUSTOMER_GROUP);
        $customerGroup = $shop->getCustomerGroup();

        $customer = null;

        if ($customerScope->getCustomerUuid() !== null) {
            //load logged in customer and set active addresses
            $customer = $this->loadCustomer($customerScope, $translationContext);

            $shippingLocation = ShippingLocation::createFromAddress($customer->getActiveShippingAddress());

            $customerGroup = $customer->getCustomerGroup();
        } else {
            //load not logged in customer with default shop configuration or with provided checkout scopes
            $shippingLocation = $this->loadShippingLocation($shop, $translationContext, $checkoutScope);
        }

        //customer group switched?
        if ($customerScope->getCustomerGroupUuid()) {
            $customerGroup = $this->customerGroupRepository->read([$customerScope->getCustomerGroupUuid()], $translationContext)
                ->get($customerScope->getCustomerGroupUuid());
        }

        //loads tax rules based on active customer group and delivery address
        //todo@dr load area based tax rules
        $criteria = new Criteria();
        $taxRules = $this->taxRepository->search($criteria, $translationContext);

        //price group discounts has to be loaded for current customer group, used for product graduations
        $criteria = new Criteria();
        $discounts = $this->priceGroupDiscountRepository->search($criteria, $translationContext);

        //detect active payment method, first check if checkout defined other payment method, otherwise validate if customer logged in, at least use shop default
        $payment = $this->getPaymentMethod($customer, $shop, $translationContext, $checkoutScope);

        //detect active delivery method, at first checkout scope, at least shop default method
        $delivery = $this->getShippingMethod($shop, $translationContext, $checkoutScope);

        $context = new ShopContext(
            $shop,
            $currency,
            $customerGroup,
            $fallbackGroup,
            $taxRules,
            $discounts,
            $payment,
            $delivery,
            $shippingLocation,
            $customer
        );

        echo '<pre>';
        print_r($context);
        exit();
        return $context;
    }

    private function getCurrency(ShopDetailStruct $shop, ?string $currencyUuid, TranslationContext $context): CurrencyBasicStruct
    {
        if ($currencyUuid === null) {
            return $shop->getCurrency();
        }

        $currency = $this->currencyRepository->read([$currencyUuid], $context);

        if (!$currency->has($currencyUuid)) {
            throw new \RuntimeException(sprintf('Currency by id %s not found', $currencyUuid));
        }

        return $currency->get($currencyUuid);
    }

    private function getPaymentMethod(?CustomerBasicStruct $customer, ShopDetailStruct $shop, TranslationContext $context, CheckoutScope $checkoutScope): PaymentMethodBasicStruct
    {
        //payment switched in checkout?
        if ($checkoutScope->getPaymentMethodUuid()) {
            return $this->paymentMethodRepository->read([$checkoutScope->getPaymentMethodUuid()], $context)
                ->get($checkoutScope->getPaymentMethodUuid());
        }

        //customer has a last payment method from previous order?
        if ($customer && $customer->getLastPaymentMethod()) {
            return $customer->getLastPaymentMethod();
        }

        //customer selected a default payment method in registration
        if ($customer && $customer->getDefaultPaymentMethod()) {
            return $customer->getDefaultPaymentMethod();
        }

        //at least use default payment method which defined for current shop
        return $shop->getPaymentMethod();
    }

    private function getShippingMethod(ShopDetailStruct $shop, TranslationContext $context, CheckoutScope $checkoutScope): ShippingMethodBasicStruct
    {
        if ($checkoutScope->getShippingMethodUuid()) {
            return $this->shippingMethodRepository->read([$checkoutScope->getShippingMethodUuid()], $context)
                ->get($checkoutScope->getShippingMethodUuid());
        }

        return $shop->getShippingMethod();
    }

    private function getTranslationContext(string $shopUuid): TranslationContext
    {
        $query = $this->connection->createQueryBuilder();
        $query->select(['uuid', 'is_default', 'fallback_locale_uuid']);
        $query->from('shop', 'shop');
        $query->where('shop.uuid = :uuid');
        $query->setParameter('uuid', $shopUuid);

        $data = $query->execute()->fetch(\PDO::FETCH_ASSOC);
        return new TranslationContext(
            $data['uuid'],
            (bool) $data['is_default'],
            $data['fallback_locale_uuid'] ?: null
        );
    }

    private function loadCustomer(CustomerScope $customerScope, TranslationContext $translationContext): CustomerBasicStruct
    {
        $customers = $this->customerRepository->read([$customerScope->getCustomerUuid()], $translationContext);
        $customer = $customers->get($customerScope->getCustomerUuid());

        if (!$customerScope->getBillingAddressUuid() && !$customerScope->getShippingAddressUuid()) {
            return $customer;
        }

        $addresses = $this->addressRepository->read(
            [$customerScope->getBillingAddressUuid(), $customerScope->getShippingAddressUuid()],
            $translationContext
        );

        //billing address changed within checkout?
        if ($customerScope->getBillingAddressUuid()) {
            $customer->setActiveBillingAddress($addresses->get($customerScope->getBillingAddressUuid()));
        }

        //shipping address changed within checkout?
        if ($customerScope->getShippingAddressUuid()) {
            $customer->setActiveShippingAddress($addresses->get($customerScope->getShippingAddressUuid()));
        }

        return $customer;
    }

    private function loadShippingLocation(
        ShopDetailStruct $shop,
        TranslationContext $translationContext,
        CheckoutScope $checkoutScope
    ): ShippingLocation {

        //allows to preview cart calculation for a specify state for not logged in customers
        if ($checkoutScope->getStateUuid()) {
            $state = $this->countryStateRepository->read([$checkoutScope->getStateUuid()], $translationContext)
                ->get($checkoutScope->getStateUuid());

            $country = $this->countryRepository->read([$state->getAreaCountryUuid()], $translationContext)
                ->get($state->getAreaCountryUuid());

            return new ShippingLocation($country, $state, null);
        }

        //allows to preview cart calculation for a specify country for not logged in customers
        if ($checkoutScope->getCountryUuid()) {
            $country = $this->countryRepository->read([$checkoutScope->getCountryUuid()], $translationContext)
                ->get($checkoutScope->getCountryUuid());

            return ShippingLocation::createFromCountry($country);
        }

        return ShippingLocation::createFromCountry($shop->getAreaCountry());
    }
}
