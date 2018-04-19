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

namespace Shopware\Shop\Struct;

use Shopware\Category\Struct\CategoryIdentity;
use Shopware\Country\Struct\CountryIdentity;
use Shopware\CustomerGroup\Struct\CustomerGroup;
use Shopware\PaymentMethod\Struct\PaymentMethod;
use Shopware\ShippingMethod\Struct\ShippingMethod;
use Shopware\ShopTemplate\Struct\ShopTemplate;

class Shop extends ShopIdentity
{
    /**
     * @var CategoryIdentity
     */
    protected $category;

    /**
     * @var ShopTemplate
     */
    protected $template;

    /**
     * @var CustomerGroup
     */
    protected $customerGroup;

    /**
     * @var ShippingMethod
     */
    protected $shippingMethod;

    /**
     * @var PaymentMethod
     */
    protected $paymentMethod;

    /**
     * @var CountryIdentity
     */
    protected $country;

    /**
     * @return bool
     */
    public function getSecure(): bool
    {
        return $this->secure;
    }

    /**
     * @return CategoryIdentity
     */
    public function getCategory(): CategoryIdentity
    {
        return $this->category;
    }

    /**
     * @param CategoryIdentity $category
     */
    public function setCategory(CategoryIdentity $category)
    {
        $this->category = $category;
    }

    /**
     * @return CustomerGroup
     */
    public function getCustomerGroup(): CustomerGroup
    {
        return $this->customerGroup;
    }

    /**
     * @param CustomerGroup $customerGroup
     */
    public function setCustomerGroup(CustomerGroup $customerGroup)
    {
        $this->customerGroup = $customerGroup;
    }

    /**
     * @return ShopTemplate
     */
    public function getTemplate(): ShopTemplate
    {
        return $this->template;
    }

    /**
     * @param ShopTemplate $template
     */
    public function setTemplate(ShopTemplate $template)
    {
        $this->template = $template;
    }

    public function getShippingMethod(): ShippingMethod
    {
        return $this->shippingMethod;
    }

    public function setShippingMethod(ShippingMethod $shippingMethod): void
    {
        $this->shippingMethod = $shippingMethod;
    }

    public function getPaymentMethod(): PaymentMethod
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(PaymentMethod $paymentMethod): void
    {
        $this->paymentMethod = $paymentMethod;
    }

    public function getCountry(): CountryIdentity
    {
        return $this->country;
    }

    public function setCountry(CountryIdentity $country): void
    {
        $this->country = $country;
    }
}
