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
use Shopware\Api\Shop\Struct\ShopBasicStruct;
use Shopware\Api\Tax\Collection\TaxBasicCollection;
use Shopware\Api\Tax\Repository\TaxRepository;
use Shopware\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Cart\Tax\TaxDetector;
use Shopware\Context\Struct\CheckoutScope;
use Shopware\Context\Struct\CustomerScope;
use Shopware\Context\Struct\ShopContext;
use Shopware\Context\Struct\ShopScope;
use Shopware\Context\Struct\StorefrontContext;
use Shopware\Defaults;
use Shopware\Framework\Struct\Uuid;

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

    /**
     * @var TaxDetector
     */
    private $taxDetector;

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
        CountryStateRepository $countryStateRepository,
        TaxDetector $taxDetector
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
        $this->taxDetector = $taxDetector;
    }

    public function create(
        string $token,
        ShopScope $shopScope,
        CustomerScope $customerScope,
        CheckoutScope $checkoutScope
    ): StorefrontContext {
        $shopContext = $this->getShopContext($shopScope->getShopId());

        /** @var ShopBasicStruct $shop */
        $shop = $this->shopRepository->readBasic([$shopScope->getShopId()], $shopContext)
            ->get($shopScope->getShopId());

        if (!$shop) {
            throw new \RuntimeException(sprintf('Shop with id %s not found or not valid!', $shopScope->getShopId()));
        }

        //load active currency, fallback to shop currency
        $currency = $this->getCurrency($shop, $shopScope->getCurrencyId(), $shopContext);

        //fallback customer group is hard coded to 'EK'
        $customerGroups = $this->customerGroupRepository->readBasic(
            [Defaults::FALLBACK_CUSTOMER_GROUP, $shop->getCustomerGroupId()],
            $shopContext
        );

        $fallbackGroup = $customerGroups->get(Defaults::FALLBACK_CUSTOMER_GROUP);
        $customerGroup = $customerGroups->get($shop->getCustomerGroupId());

        $customer = null;

        if ($customerScope->getCustomerId() !== null) {
            //load logged in customer and set active addresses
            $customer = $this->loadCustomer($customerScope, $shopContext);

            $shippingLocation = ShippingLocation::createFromAddress($customer->getActiveShippingAddress());

            $customerGroup = $customer->getGroup();
        } else {
            //load not logged in customer with default shop configuration or with provided checkout scopes
            $shippingLocation = $this->loadShippingLocation($shop, $shopContext, $checkoutScope);
        }

        //customer group switched?
        if ($customerScope->getCustomerGroupId() !== null) {
            $customerGroup = $this->customerGroupRepository->readBasic([$customerScope->getCustomerGroupId()], $shopContext)
                ->get($customerScope->getCustomerGroupId());
        }

        //loads tax rules based on active customer group and delivery address
        //todo@dr load area based tax rules
        $criteria = new Criteria();
        $taxRules = $this->taxRepository->search($criteria, $shopContext);

        //detect active payment method, first check if checkout defined other payment method, otherwise validate if customer logged in, at least use shop default
        $payment = $this->getPaymentMethod($customer, $shop, $shopContext, $checkoutScope);

        //detect active delivery method, at first checkout scope, at least shop default method
        $delivery = $this->getShippingMethod($shop, $shopContext, $checkoutScope);

        $context = new StorefrontContext(
            $token,
            $shop,
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

    private function getCurrency(ShopBasicStruct $shop, ?string $currencyId, ShopContext $context): CurrencyBasicStruct
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

    private function getPaymentMethod(?CustomerBasicStruct $customer, ShopBasicStruct $shop, ShopContext $context, CheckoutScope $checkoutScope): PaymentMethodBasicStruct
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

        return $this->paymentMethodRepository->readBasic([$shop->getPaymentMethodId()], $context)
            ->get($shop->getPaymentMethodId());
    }

    private function getShippingMethod(ShopBasicStruct $shop, ShopContext $context, CheckoutScope $checkoutScope): ShippingMethodBasicStruct
    {
        $id = $checkoutScope->getShippingMethodId() ?? $shop->getShippingMethodId();

        return $this->shippingMethodRepository->readBasic([$id], $context)
            ->get($id);
    }

    private function getShopContext(string $shopId): ShopContext
    {
        $query = $this->connection->createQueryBuilder();
        $query->select([
            'shop.id as shop_id',
            'shop.locale_id as shop_locale_id',
            'shop.currency_id as shop_currency_id',
            'shop.fallback_translation_id as shop_fallback_translation_id',
            'shop.catalog_ids as shop_catalog_ids',
            'currency.factor as shop_currency_factor',
        ]);
        $query->from('shop', 'shop');
        $query->innerJoin('shop', 'currency', 'currency', 'shop.currency_id = currency.id');
        $query->where('shop.id = :id');
        $query->setParameter('id', Uuid::fromStringToBytes($shopId));

        $data = $query->execute()->fetch(\PDO::FETCH_ASSOC);

        return new ShopContext(
            Uuid::fromBytesToHex($data['shop_id']),
            json_decode($data['shop_catalog_ids'], true),
            [],
            Uuid::fromBytesToHex($data['shop_currency_id']),
            Defaults::LANGUAGE,
            null,
            Defaults::LIVE_VERSION,
            (float) $data['shop_currency_factor']
        );
    }

    private function loadCustomer(CustomerScope $customerScope, ShopContext $shopContext): ?CustomerBasicStruct
    {
        $customers = $this->customerRepository->readBasic([$customerScope->getCustomerId()], $shopContext);
        $customer = $customers->get($customerScope->getCustomerId());

        if (!$customer) {
            return $customer;
        }

        if ($customerScope->getBillingAddressId() === null && $customerScope->getShippingAddressId() === null) {
            return $customer;
        }

        $addresses = $this->addressRepository->readBasic(
            [$customerScope->getBillingAddressId(), $customerScope->getShippingAddressId()],
            $shopContext
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
        ShopBasicStruct $shop,
        ShopContext $shopContext,
        CheckoutScope $checkoutScope
    ): ShippingLocation {
        //allows to preview cart calculation for a specify state for not logged in customers
        if ($checkoutScope->getStateId() !== null) {
            $state = $this->countryStateRepository->readBasic([$checkoutScope->getStateId()], $shopContext)
                ->get($checkoutScope->getStateId());

            $country = $this->countryRepository->readBasic([$state->getCountryId()], $shopContext)
                ->get($state->getCountryId());

            return new ShippingLocation($country, $state, null);
        }

        $countryId = $checkoutScope->getCountryId() ?? $shop->getCountryId();

        $country = $this->countryRepository->readBasic([$countryId], $shopContext)
            ->get($countryId);

        return ShippingLocation::createFromCountry($country);
    }
}
