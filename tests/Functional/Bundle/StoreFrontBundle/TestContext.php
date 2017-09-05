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

namespace Shopware\Tests\Functional\Bundle\StoreFrontBundle;

use Shopware\Context\Struct\ShopContext;

class TestContext extends ShopContext
{
    /**
     * @param \Shopware\CountryArea\Struct\CountryArea $area
     */
    public function setArea($area)
    {
        $this->area = $area;
    }

    /**
     * @param string $baseUrl
     */
    public function setBaseUrl($baseUrl)
    {
        $this->baseUrl = $baseUrl;
    }

    /**
     * @param \Shopware\Country\Struct\Country $country
     */
    public function setCountry($country)
    {
        $this->country = $country;
    }

    /**
     * @param \Shopware\Currency\Struct\Currency $currency
     */
    public function setCurrency($currency)
    {
        $this->currency = $currency;
    }

    /**
     * @param \Shopware\CustomerGroup\Struct\CustomerGroup $currentCustomerGroup
     */
    public function setCurrentCustomerGroup($currentCustomerGroup)
    {
        $this->currentCustomerGroup = $currentCustomerGroup;
    }

    /**
     * @param \Shopware\CustomerGroup\Struct\CustomerGroup $fallbackCustomerGroup
     */
    public function setFallbackCustomerGroup($fallbackCustomerGroup)
    {
        $this->fallbackCustomerGroup = $fallbackCustomerGroup;
    }

    /**
     * @param \Shopware\PriceGroup\Struct\PriceGroup[] $priceGroups
     */
    public function setPriceGroups($priceGroups)
    {
        $this->priceGroupDiscounts = $priceGroups;
    }

    /**
     * @param \Shopware\Shop\Struct\Shop $shop
     */
    public function setShop($shop)
    {
        $this->shop = $shop;
    }

    /**
     * @param \Shopware\CountryState\Struct\CountryState $state
     */
    public function setState($state)
    {
        $this->state = $state;
    }

    /**
     * @param \Shopware\Tax\Struct\Tax[] $taxRules
     */
    public function setTaxRules($taxRules)
    {
        $this->taxRules = $taxRules;
    }
}
