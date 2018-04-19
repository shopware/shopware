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
use Shopware\Customer\Struct\Customer;
use Shopware\CustomerGroup\Struct\CustomerGroup;
use Shopware\Framework\Struct\Struct;
use Shopware\PaymentMethod\Struct\PaymentMethod;
use Shopware\PriceGroup\Struct\PriceGroupCollection;
use Shopware\ShippingMethod\Struct\ShippingMethod;
use Shopware\Shop\Struct\Shop;
use Shopware\Tax\Struct\Tax;
use Shopware\Tax\Struct\TaxCollection;

/**
 * @category  Shopware
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class ShopContext extends Struct
{
    /**
     * @var CustomerGroup
     */
    protected $currentCustomerGroup;

    /**
     * @var \Shopware\CustomerGroup\Struct\CustomerGroup
     */
    protected $fallbackCustomerGroup;

    /**
     * @var \Shopware\Currency\Struct\Currency
     */
    protected $currency;

    /**
     * @var \Shopware\Shop\Struct\Shop
     */
    protected $shop;

    /**
     * @var TaxCollection
     */
    protected $taxRules;

    /**
     * @var PriceGroupCollection
     */
    protected $priceGroups;

    /**
     * @var \Shopware\Customer\Struct\Customer|null
     */
    protected $customer;

    /**
     * @var \Shopware\PaymentMethod\Struct\PaymentMethod
     */
    protected $paymentMethod;

    /**
     * @var ShippingMethod
     */
    protected $shippingMethod;

    /**
     * @var ShippingLocation
     */
    protected $shippingLocation;

    public function __construct(
        Shop $shop,
        Currency $currency,
        CustomerGroup $currentCustomerGroup,
        CustomerGroup $fallbackCustomerGroup,
        TaxCollection $taxRules,
        PriceGroupCollection $priceGroups,
        PaymentMethod $paymentMethod,
        ShippingMethod $shippingMethod,
        ShippingLocation $shippingLocation,
        ?Customer $customer
    ) {
        $this->currentCustomerGroup = $currentCustomerGroup;
        $this->fallbackCustomerGroup = $fallbackCustomerGroup;
        $this->currency = $currency;
        $this->shop = $shop;
        $this->taxRules = $taxRules;
        $this->priceGroups = $priceGroups;
        $this->customer = $customer;
        $this->paymentMethod = $paymentMethod;
        $this->shippingMethod = $shippingMethod;
        $this->shippingLocation = $shippingLocation;
    }

    public function getShop(): Shop
    {
        return $this->shop;
    }

    public function getCurrency(): Currency
    {
        return $this->currency;
    }

    public function getCurrentCustomerGroup(): CustomerGroup
    {
        return $this->currentCustomerGroup;
    }

    public function getFallbackCustomerGroup(): CustomerGroup
    {
        return $this->fallbackCustomerGroup;
    }

    public function getPriceGroups(): PriceGroupCollection
    {
        return $this->priceGroups;
    }

    /**
     * {@inheritdoc}
     */
    public function getTaxRule(int $taxId): Tax
    {
        return $this->taxRules->get($taxId);
    }

    public function getCustomer(): ? Customer
    {
        return $this->customer;
    }

    public function getPaymentMethod(): PaymentMethod
    {
        return $this->paymentMethod;
    }

    public function getShippingMethod(): ShippingMethod
    {
        return $this->shippingMethod;
    }

    public function getTranslationContext(): TranslationContext
    {
        return TranslationContext::createFromShop($this->shop);
    }

    public function getShippingLocation(): ShippingLocation
    {
        return $this->shippingLocation;
    }
}
