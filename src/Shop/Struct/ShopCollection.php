<?php
declare(strict_types=1);
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

use Shopware\Category\Struct\CategoryIdentityCollection;
use Shopware\Country\Struct\CountryIdentityCollection;
use Shopware\CustomerGroup\Struct\CustomerGroupCollection;
use Shopware\PaymentMethod\Struct\PaymentMethodCollection;
use Shopware\ShippingMethod\Struct\ShippingMethodCollection;
use Shopware\ShopTemplate\Struct\ShopTemplateCollection;

class ShopCollection extends ShopIdentityCollection
{
    /**
     * @var Shop[]
     */
    protected $elements = [];

    public function getCategories(): CategoryIdentityCollection
    {
        return new CategoryIdentityCollection(
            $this->fmap(function(Shop $shop) {
                return $shop->getCategory();
            })
        );
    }

    public function getTemplates(): ShopTemplateCollection
    {
        return new ShopTemplateCollection(
            $this->fmap(function(Shop $shop) {
                return $shop->getTemplate();
            })
        );
    }

    public function getCustomerGroups(): CustomerGroupCollection
    {
        return new CustomerGroupCollection(
            $this->fmap(function(Shop $shop) {
                return $shop->getCustomerGroup();
            })
        );
    }

    public function getPaymentMethods(): PaymentMethodCollection
    {
        return new PaymentMethodCollection(
            $this->fmap(function(Shop $shop) {
                return $shop->getPaymentMethod();
            })
        );
    }

    public function getShippingMethods(): ShippingMethodCollection
    {
        return new ShippingMethodCollection(
            $this->fmap(function(Shop $shop) {
                return $shop->getShippingMethod();
            })
        );
    }

    public function getCountries(): CountryIdentityCollection
    {
        return new CountryIdentityCollection(
            $this->fmap(function(Shop $shop) {
                return $shop->getCountry();
            })
        );
    }
}
