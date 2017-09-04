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

namespace Shopware\Shop\Struct;

use Shopware\AreaCountry\Struct\AreaCountryBasicCollection;
use Shopware\Category\Struct\CategoryBasicCollection;
use Shopware\Currency\Struct\CurrencyBasicCollection;
use Shopware\CustomerGroup\Struct\CustomerGroupBasicCollection;
use Shopware\Locale\Struct\LocaleBasicCollection;
use Shopware\PaymentMethod\Struct\PaymentMethodBasicCollection;
use Shopware\ShippingMethod\Struct\ShippingMethodBasicCollection;
use Shopware\ShopTemplate\Struct\ShopTemplateBasicCollection;

class ShopDetailCollection extends ShopBasicCollection
{
    /**
     * @var ShopDetailStruct[]
     */
    protected $elements = [];

    public function getCategories(): CategoryBasicCollection
    {
        return new CategoryBasicCollection(
            $this->fmap(function (ShopDetailStruct $shop) {
                return $shop->getCategory();
            })
        );
    }

    public function getFallbackLocales(): LocaleBasicCollection
    {
        return new LocaleBasicCollection(
            $this->fmap(function (ShopDetailStruct $shop) {
                return $shop->getFallbackLocale();
            })
        );
    }

    public function getShippingMethods(): ShippingMethodBasicCollection
    {
        return new ShippingMethodBasicCollection(
            $this->fmap(function (ShopDetailStruct $shop) {
                return $shop->getShippingMethod();
            })
        );
    }

    public function getShopTemplates(): ShopTemplateBasicCollection
    {
        return new ShopTemplateBasicCollection(
            $this->fmap(function (ShopDetailStruct $shop) {
                return $shop->getShopTemplate();
            })
        );
    }

    public function getAreaCountries(): AreaCountryBasicCollection
    {
        return new AreaCountryBasicCollection(
            $this->fmap(function (ShopDetailStruct $shop) {
                return $shop->getAreaCountry();
            })
        );
    }

    public function getPaymentMethods(): PaymentMethodBasicCollection
    {
        return new PaymentMethodBasicCollection(
            $this->fmap(function (ShopDetailStruct $shop) {
                return $shop->getPaymentMethod();
            })
        );
    }

    public function getCustomerGroups(): CustomerGroupBasicCollection
    {
        return new CustomerGroupBasicCollection(
            $this->fmap(function (ShopDetailStruct $shop) {
                return $shop->getCustomerGroup();
            })
        );
    }

    public function getCurrencyUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getCurrencyUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getCurrencies(): CurrencyBasicCollection
    {
        $collection = new CurrencyBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getCurrencies()->getIterator()->getArrayCopy());
        }

        return $collection;
    }
}
