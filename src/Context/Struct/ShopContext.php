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

namespace Shopware\Context\Struct;

use Shopware\Cart\Delivery\ShippingLocation;
use Shopware\Currency\Struct\Currency;
use Shopware\Currency\Struct\CurrencyBasicStruct;
use Shopware\Customer\Struct\Customer;
use Shopware\Customer\Struct\CustomerBasicStruct;
use Shopware\CustomerGroup\Struct\CustomerGroup;
use Shopware\CustomerGroup\Struct\CustomerGroupBasicStruct;
use Shopware\Framework\Struct\Struct;
use Shopware\PaymentMethod\Struct\PaymentMethod;
use Shopware\PaymentMethod\Struct\PaymentMethodBasicStruct;
use Shopware\PriceGroup\Struct\PriceGroupCollection;
use Shopware\PriceGroupDiscount\Struct\PriceGroupDiscountBasicCollection;
use Shopware\ShippingMethod\Struct\ShippingMethod;
use Shopware\ShippingMethod\Struct\ShippingMethodBasicStruct;
use Shopware\Shop\Struct\Shop;
use Shopware\Shop\Struct\ShopDetailStruct;
use Shopware\Tax\Struct\Tax;
use Shopware\Tax\Struct\TaxBasicCollection;
use Shopware\Tax\Struct\TaxCollection;

/**
 * @category  Shopware
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class ShopContext extends Struct
{
    /**
     * @var CustomerGroupBasicStruct
     */
    protected $currentCustomerGroup;

    /**
     * @var CustomerGroupBasicStruct
     */
    protected $fallbackCustomerGroup;

    /**
     * @var CurrencyBasicStruct
     */
    protected $currency;

    /**
     * @var ShopDetailStruct
     */
    protected $shop;

    /**
     * @var TaxBasicCollection
     */
    protected $taxRules;

    /**
     * @var PriceGroupDiscountBasicCollection
     */
    protected $priceGroupDiscounts;

    /**
     * @var CustomerBasicStruct|null
     */
    protected $customer;

    /**
     * @var PaymentMethodBasicStruct
     */
    protected $paymentMethod;

    /**
     * @var ShippingMethodBasicStruct
     */
    protected $shippingMethod;

    /**
     * @var ShippingLocation
     */
    protected $shippingLocation;

    public function __construct(
        ShopDetailStruct $shop,
        CurrencyBasicStruct $currency,
        CustomerGroupBasicStruct $currentCustomerGroup,
        CustomerGroupBasicStruct $fallbackCustomerGroup,
        TaxBasicCollection $taxRules,
        PriceGroupDiscountBasicCollection $priceGroupDiscounts,
        PaymentMethodBasicStruct $paymentMethod,
        ShippingMethodBasicStruct $shippingMethod,
        ShippingLocation $shippingLocation,
        ?CustomerBasicStruct $customer
    ) {
        $this->currentCustomerGroup = $currentCustomerGroup;
        $this->fallbackCustomerGroup = $fallbackCustomerGroup;
        $this->currency = $currency;
        $this->shop = $shop;
        $this->taxRules = $taxRules;
        $this->priceGroupDiscounts = $priceGroupDiscounts;
        $this->customer = $customer;
        $this->paymentMethod = $paymentMethod;
        $this->shippingMethod = $shippingMethod;
        $this->shippingLocation = $shippingLocation;
    }

    public function getCurrentCustomerGroup(): CustomerGroupBasicStruct
    {
        return $this->currentCustomerGroup;
    }

    public function getFallbackCustomerGroup(): CustomerGroupBasicStruct
    {
        return $this->fallbackCustomerGroup;
    }

    public function getCurrency(): CurrencyBasicStruct
    {
        return $this->currency;
    }

    public function getShop(): ShopDetailStruct
    {
        return $this->shop;
    }

    public function getTaxRules(): TaxBasicCollection
    {
        return $this->taxRules;
    }

    public function getPriceGroupDiscounts(): PriceGroupDiscountBasicCollection
    {
        return $this->priceGroupDiscounts;
    }

    public function getCustomer(): ?CustomerBasicStruct
    {
        return $this->customer;
    }

    public function getPaymentMethod(): PaymentMethodBasicStruct
    {
        return $this->paymentMethod;
    }

    public function getShippingMethod(): ShippingMethodBasicStruct
    {
        return $this->shippingMethod;
    }

    public function getShippingLocation(): ShippingLocation
    {
        return $this->shippingLocation;
    }

    public function getTranslationContext(): TranslationContext
    {
        return TranslationContext::createFromShop($this->shop);
    }
}
