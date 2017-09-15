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

use Shopware\AreaCountry\Struct\AreaCountryBasicStruct;
use Shopware\Category\Struct\CategoryBasicStruct;
use Shopware\Currency\Struct\CurrencyBasicCollection;
use Shopware\CustomerGroup\Struct\CustomerGroupBasicStruct;
use Shopware\Locale\Struct\LocaleBasicStruct;
use Shopware\PaymentMethod\Struct\PaymentMethodBasicStruct;
use Shopware\ShippingMethod\Struct\ShippingMethodBasicStruct;
use Shopware\ShopTemplate\Struct\ShopTemplateBasicStruct;

class ShopDetailStruct extends ShopBasicStruct
{
    /**
     * @var LocaleBasicStruct|null
     */
    protected $fallbackLocale;

    /**
     * @var CategoryBasicStruct
     */
    protected $category;

    /**
     * @var CustomerGroupBasicStruct
     */
    protected $customerGroup;

    /**
     * @var PaymentMethodBasicStruct
     */
    protected $paymentMethod;

    /**
     * @var ShippingMethodBasicStruct
     */
    protected $shippingMethod;

    /**
     * @var AreaCountryBasicStruct
     */
    protected $country;

    /**
     * @var ShopTemplateBasicStruct
     */
    protected $template;

    /**
     * @var string[]
     */
    protected $availableCurrencyUuids = [];

    /**
     * @var CurrencyBasicCollection
     */
    protected $availableCurrencies;

    public function __construct()
    {
        $this->availableCurrencies = new CurrencyBasicCollection();
    }

    public function getFallbackLocale(): ?LocaleBasicStruct
    {
        return $this->fallbackLocale;
    }

    public function setFallbackLocale(?LocaleBasicStruct $fallbackLocale): void
    {
        $this->fallbackLocale = $fallbackLocale;
    }

    public function getCategory(): CategoryBasicStruct
    {
        return $this->category;
    }

    public function setCategory(CategoryBasicStruct $category): void
    {
        $this->category = $category;
    }

    public function getCustomerGroup(): CustomerGroupBasicStruct
    {
        return $this->customerGroup;
    }

    public function setCustomerGroup(CustomerGroupBasicStruct $customerGroup): void
    {
        $this->customerGroup = $customerGroup;
    }

    public function getPaymentMethod(): PaymentMethodBasicStruct
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(PaymentMethodBasicStruct $paymentMethod): void
    {
        $this->paymentMethod = $paymentMethod;
    }

    public function getShippingMethod(): ShippingMethodBasicStruct
    {
        return $this->shippingMethod;
    }

    public function setShippingMethod(ShippingMethodBasicStruct $shippingMethod): void
    {
        $this->shippingMethod = $shippingMethod;
    }

    public function getCountry(): AreaCountryBasicStruct
    {
        return $this->country;
    }

    public function setCountry(AreaCountryBasicStruct $country): void
    {
        $this->country = $country;
    }

    public function getTemplate(): ShopTemplateBasicStruct
    {
        return $this->template;
    }

    public function setTemplate(ShopTemplateBasicStruct $template): void
    {
        $this->template = $template;
    }

    public function getAvailableCurrencyUuids(): array
    {
        return $this->availableCurrencyUuids;
    }

    public function setAvailableCurrencyUuids(array $availableCurrencyUuids): void
    {
        $this->availableCurrencyUuids = $availableCurrencyUuids;
    }

    public function getAvailableCurrencies(): CurrencyBasicCollection
    {
        return $this->availableCurrencies;
    }

    public function setAvailableCurrencies(CurrencyBasicCollection $availableCurrencies): void
    {
        $this->availableCurrencies = $availableCurrencies;
    }
}
