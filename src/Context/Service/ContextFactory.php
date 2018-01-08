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
use Ramsey\Uuid\Uuid;
use Shopware\Api\Country\Repository\CountryRepository;
use Shopware\Api\Country\Repository\CountryStateRepository;
use Shopware\Api\Currency\Repository\CurrencyRepository;
use Shopware\Api\Currency\Struct\CurrencyBasicStruct;
use Shopware\Api\Customer\Repository\CustomerAddressRepository;
use Shopware\Api\Customer\Repository\CustomerGroupRepository;
use Shopware\Api\Customer\Repository\CustomerRepository;
use Shopware\Api\Customer\Struct\CustomerBasicStruct;
use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Payment\Repository\PaymentMethodRepository;
use Shopware\Api\Payment\Struct\PaymentMethodBasicStruct;
use Shopware\Api\Shipping\Repository\ShippingMethodRepository;
use Shopware\Api\Shipping\Struct\ShippingMethodBasicStruct;
use Shopware\Api\Shop\Repository\ShopRepository;
use Shopware\Api\Shop\Struct\ShopDetailStruct;
use Shopware\Api\Tax\Collection\TaxBasicCollection;
use Shopware\Api\Tax\Repository\TaxRepository;
use Shopware\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Context\Struct\CheckoutScope;
use Shopware\Context\Struct\CustomerScope;
use Shopware\Context\Struct\ShopContext;
use Shopware\Context\Struct\ShopScope;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Storefront\Context\StorefrontContextService;

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

    public function __construct(
        ShopRepository $shopRepository,
        CurrencyRepository $currencyRepository,
        CustomerRepository $customerRepository,
        CustomerGroupRepository $customerGroupRepository,
        CountryRepository $countryRepository,
        TaxRepository $taxRepository,
        CustomerAddressRepository $addressRepository,
        PaymentMethodRepository $paymentMethodRepository,
        ShippingMethodRepository $shippingMethodRepository,
        Connection $connection,
        CountryStateRepository $countryStateRepository
    ) {
        $this->shopRepository = $shopRepository;
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
    }

    public function create(
        ShopScope $shopScope,
        CustomerScope $customerScope,
        CheckoutScope $checkoutScope
    ): ShopContext {
        $translationContext = $this->getTranslationContext($shopScope->getShopId());

        //select shop with all fallbacks
        /** @var ShopDetailStruct $shop */
        $shop = $this->shopRepository->readDetail([$shopScope->getShopId()], $translationContext)
            ->get($shopScope->getShopId());

        if (!$shop) {
            throw new \RuntimeException(sprintf('Shop with id %s not found or not valid!', $shopScope->getShopId()));
        }

        //load active currency, fallback to shop currency
        $currency = $this->getCurrency($shop, $shopScope->getCurrencyId(), $translationContext);

        //fallback customer group is hard coded to 'EK'
        $customerGroups = $this->customerGroupRepository->readBasic(
            [StorefrontContextService::FALLBACK_CUSTOMER_GROUP],
            $translationContext
        );

        $fallbackGroup = $customerGroups->get(StorefrontContextService::FALLBACK_CUSTOMER_GROUP);
        $customerGroup = $shop->getCustomerGroup();

        $customer = null;

        if ($customerScope->getCustomerId() !== null) {
            //load logged in customer and set active addresses
            $customer = $this->loadCustomer($customerScope, $translationContext);

            $shippingLocation = ShippingLocation::createFromAddress($customer->getActiveShippingAddress());

            $customerGroup = $customer->getGroup();
        } else {
            //load not logged in customer with default shop configuration or with provided checkout scopes
            $shippingLocation = $this->loadShippingLocation($shop, $translationContext, $checkoutScope);
        }

        //customer group switched?
        if ($customerScope->getCustomerGroupId() !== null) {
            $customerGroup = $this->customerGroupRepository->readBasic([$customerScope->getCustomerGroupId()], $translationContext)
                ->get($customerScope->getCustomerGroupId());
        }

        //loads tax rules based on active customer group and delivery address
        //todo@dr load area based tax rules
        $criteria = new Criteria();
        $taxRules = $this->taxRepository->search($criteria, $translationContext);

        //detect active payment method, first check if checkout defined other payment method, otherwise validate if customer logged in, at least use shop default
        $payment = $this->getPaymentMethod($customer, $shop, $translationContext, $checkoutScope);

        //detect active delivery method, at first checkout scope, at least shop default method
        $delivery = $this->getShippingMethod($shop, $translationContext, $checkoutScope);

        $context = new ShopContext(
            $shop,
            $currency,
            $customerGroup,
            $fallbackGroup,
            new TaxBasicCollection($taxRules->getElements()),
            $payment,
            $delivery,
            $shippingLocation,
            $customer
        );

        return $context;
    }

    private function getCurrency(ShopDetailStruct $shop, ?string $currencyId, TranslationContext $context): CurrencyBasicStruct
    {
        if ($currencyId === null) {
            return $shop->getCurrency();
        }

        $currency = $this->currencyRepository->readBasic([$currencyId], $context);

        if (!$currency->has($currencyId)) {
            return $shop->getCurrency();
        }

        return $currency->get($currencyId);
    }

    private function getPaymentMethod(?CustomerBasicStruct $customer, ShopDetailStruct $shop, TranslationContext $context, CheckoutScope $checkoutScope): PaymentMethodBasicStruct
    {
        //payment switched in checkout?
        if ($checkoutScope->getPaymentMethodId() !== null) {
            return $this->paymentMethodRepository->readBasic([$checkoutScope->getPaymentMethodId()], $context)
                ->get($checkoutScope->getPaymentMethodId());
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
        if ($checkoutScope->getShippingMethodId() !== null) {
            return $this->shippingMethodRepository->readBasic([$checkoutScope->getShippingMethodId()], $context)
                ->get($checkoutScope->getShippingMethodId());
        }

        return $shop->getShippingMethod();
    }

    private function getTranslationContext(string $shopId): TranslationContext
    {
        $query = $this->connection->createQueryBuilder();
        $query->select(['id', 'is_default', 'fallback_translation_id']);
        $query->from('shop', 'shop');
        $query->where('shop.id = :id');
        $query->setParameter('id', Uuid::fromString($shopId)->getBytes());

        $data = $query->execute()->fetch(\PDO::FETCH_ASSOC);

        return new TranslationContext(
            Uuid::fromBytes($data['id'])->toString(),
            (bool) $data['is_default'],
            $data['fallback_translation_id'] ? Uuid::fromBytes($data['fallback_translation_id'])->toString() : null
        );
    }

    private function loadCustomer(CustomerScope $customerScope, TranslationContext $translationContext): ?CustomerBasicStruct
    {
        $customers = $this->customerRepository->readBasic([$customerScope->getCustomerId()], $translationContext);
        $customer = $customers->get($customerScope->getCustomerId());

        if (!$customer) {
            return $customer;
        }

        if ($customerScope->getBillingAddressId() === null && $customerScope->getShippingAddressId() === null) {
            return $customer;
        }

        $addresses = $this->addressRepository->readBasic(
            [$customerScope->getBillingAddressId(), $customerScope->getShippingAddressId()],
            $translationContext
        );

        //billing address changed within checkout?
        if ($customerScope->getBillingAddressId() !== null) {
            $customer->setActiveBillingAddress($addresses->get($customerScope->getBillingAddressId()));
        }

        //shipping address changed within checkout?
        if ($customerScope->getShippingAddressId() !== null) {
            $customer->setActiveShippingAddress($addresses->get($customerScope->getShippingAddressId()));
        }

        return $customer;
    }

    private function loadShippingLocation(
        ShopDetailStruct $shop,
        TranslationContext $translationContext,
        CheckoutScope $checkoutScope
    ): ShippingLocation {
        //allows to preview cart calculation for a specify state for not logged in customers
        if ($checkoutScope->getStateId() !== null) {
            $state = $this->countryStateRepository->readBasic([$checkoutScope->getStateId()], $translationContext)
                ->get($checkoutScope->getStateId());

            $country = $this->countryRepository->readBasic([$state->getCountryId()], $translationContext)
                ->get($state->getCountryId());

            return new ShippingLocation($country, $state, null);
        }

        //allows to preview cart calculation for a specify country for not logged in customers
        if ($checkoutScope->getCountryId() !== null) {
            $country = $this->countryRepository->readBasic([$checkoutScope->getCountryId()], $translationContext)
                ->get($checkoutScope->getCountryId());

            return ShippingLocation::createFromCountry($country);
        }

        return ShippingLocation::createFromCountry($shop->getCountry());
    }
}
