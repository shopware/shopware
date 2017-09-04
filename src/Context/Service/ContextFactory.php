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
use Shopware\Customer\CustomerRepository;
use Shopware\Customer\Struct\Customer;
use Shopware\CustomerAddress\CustomerAddressRepository;
use Shopware\CustomerGroup\CustomerGroupRepository;
use Shopware\PaymentMethod\PaymentMethodRepository;
use Shopware\PaymentMethod\Struct\PaymentMethod;
use Shopware\PriceGroup\PriceGroupRepository;
use Shopware\ShippingMethod\ShippingMethodRepository;
use Shopware\ShippingMethod\Struct\ShippingMethod;
use Shopware\Shop\ShopRepository;
use Shopware\Shop\Struct\Shop;
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
     * @var PriceGroupRepository
     */
    private $priceGroupRepository;

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
        PriceGroupRepository $priceGroupRepository,
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
        $this->priceGroupRepository = $priceGroupRepository;
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
        $translationContext = $this->getTranslationContext($shopScope->getShopId());

        //select shop with all fallbacks
        $shop = $this->shopRepository->read([$shopScope->getShopId()], $translationContext)
            ->get($shopScope->getShopId());

        if (!$shop) {
            throw new \RuntimeException(sprintf('Shop with id %s not found or not valid!', $shopScope->getShopId()));
        }

        //load active currency, fallback to shop currency
        $currency = $this->getCurrency($shop, $shopScope->getCurrencyId(), $translationContext);

        //fallback customer group is hard coded to 'EK'
        $fallbackGroup = $this->customerGroupRepository->read([StorefrontContextService::FALLBACK_CUSTOMER_GROUP], $translationContext)
            ->getByKey(StorefrontContextService::FALLBACK_CUSTOMER_GROUP);

        $customer = null;

        $customerGroup = $shop->getCustomerGroup();

        if ($customerScope->getCustomerId() !== null) {
            //load logged in customer and set active addresses
            $customer = $this->loadCustomer($customerScope, $translationContext);

            $shippingLocation = ShippingLocation::createFromAddress($customer->getActiveShippingAddress());

            $customerGroup = $customer->getCustomerGroup();
        } else {
            //load not logged in customer with default shop configuration or with provided checkout scopes
            $shippingLocation = $this->loadShippingLocation($shop, $translationContext, $checkoutScope);
        }

        //customer group switched?
        if ($customerScope->getCustomerGroupKey()) {
            $customerGroup = $this->customerGroupRepository->read([$customerScope->getCustomerGroupKey()], $translationContext)
                ->getByKey($customerScope->getCustomerGroupKey());
        }

        //loads tax rules based on active customer group and delivery address
        $taxRules = $this->taxRepository->getRules($customerGroup, $shippingLocation);

        //price group discounts has to be loaded for current customer group, used for product graduations
        $priceGroups = $this->priceGroupRepository->read($customerGroup, $translationContext);

        //detect active payment method, first check if checkout defined other payment method, otherwise validate if customer logged in, at least use shop default
        $payment = $this->getPayment($customer, $shop, $translationContext, $checkoutScope);

        //detect active delivery method, at first checkout scope, at least shop default method
        $delivery = $this->getDelivery($shop, $translationContext, $checkoutScope);

        return new ShopContext(
            $shop,
            $currency,
            $customerGroup,
            $fallbackGroup,
            $taxRules,
            $priceGroups,
            $payment,
            $delivery,
            $shippingLocation,
            $customer
        );
    }

    private function getCurrency(Shop $shop, ?int $currencyId, TranslationContext $context): Currency
    {
        if ($currencyId === null) {
            return $shop->getCurrency();
        }

        $currency = $this->currencyRepository->read([$currencyId], $context);

        if (!$currency->has($currencyId)) {
            throw new \RuntimeException(sprintf('Currency by id %s not found', $currencyId));
        }

        return $currency->get($currencyId);
    }

    private function getPayment(?Customer $customer, Shop $shop, TranslationContext $context, CheckoutScope $checkoutScope): PaymentMethod
    {
        //payment switched in checkout?
        if ($checkoutScope->getPaymentId()) {
            return $this->paymentMethodRepository->read([$checkoutScope->getPaymentId()], $context)
                ->get($checkoutScope->getPaymentId());
        }

        //customer has a last payment method from previous order?
        if ($customer && $customer->getLastPaymentMethod()) {
            return $customer->getLastPaymentMethod();
        }

        //customer selected a default payment method in registration
        if ($customer && $customer->getPresetPaymentMethod()) {
            return $customer->getPresetPaymentMethod();
        }

        //at least use default payment method which defined for current shop
        return $shop->getPaymentMethod();
    }

    private function getDelivery(Shop $shop, TranslationContext $context, CheckoutScope $checkoutScope): ShippingMethod
    {
        if ($checkoutScope->getDispatchId()) {
            return $this->shippingMethodRepository->read([$checkoutScope->getDispatchId()], $context)
                ->get($checkoutScope->getDispatchId());
        }

        return $shop->getShippingMethod();
    }

    private function getTranslationContext(int $shopId): TranslationContext
    {
        $query = $this->connection->createQueryBuilder();
        $query->select(['id', '`default`', 'fallback_id']);
        $query->from('s_core_shops', 'shop');
        $query->where('shop.id = :id');
        $query->setParameter('id', $shopId);

        $data = $query->execute()->fetch(\PDO::FETCH_ASSOC);

        return new TranslationContext(
            (int) $data['id'],
            (bool) $data['default'],
            $data['fallback_id'] ? (int) $data['fallback_id'] : null
        );
    }

    private function loadCustomer(CustomerScope $customerScope, TranslationContext $translationContext): Customer
    {
        $customers = $this->customerRepository->read([$customerScope->getCustomerId()], $translationContext);

        $customer = $customers->get($customerScope->getCustomerId());

        //billing address changed within checkout?
        if ($customerScope->getBillingId()) {
            $addresses = $this->addressRepository->read([$customerScope->getBillingId()], $translationContext);
            $customer->setActiveBillingAddress($addresses->get($customerScope->getBillingId()));
        }

        //shipping address changed within checkout?
        if ($customerScope->getShippingId()) {
            $addresses = $this->addressRepository->read([$customerScope->getShippingId()], $translationContext);
            $customer->setActiveShippingAddress($addresses->get($customerScope->getShippingId()));
        }

        return $customer;
    }

    private function loadShippingLocation(
        Shop $shop,
        TranslationContext $translationContext,
        CheckoutScope $checkoutScope
    ): ShippingLocation {
        //allows to preview cart calculation for a specify state for not logged in customers
        if ($checkoutScope->getStateId()) {
            $states = $this->countryStateRepository->read(
                [$checkoutScope->getStateId()],
                $translationContext
            );

            return ShippingLocation::createFromState(
                $states->get($checkoutScope->getStateId())
            );
        }

        //allows to preview cart calculation for a specify country for not logged in customers
        if ($checkoutScope->getCountryId()) {
            $countries = $this->countryRepository->read(
                [$checkoutScope->getCountryId()],
                $translationContext
            );

            return ShippingLocation::createFromCountry(
                $countries->get($checkoutScope->getCountryId())
            );
        }

        return ShippingLocation::createFromCountry($shop->getCountry());
    }
}
