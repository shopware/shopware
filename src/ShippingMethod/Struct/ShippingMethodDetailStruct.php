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

namespace Shopware\ShippingMethod\Struct;

use Shopware\AreaCountry\Struct\AreaCountryBasicCollection;
use Shopware\Category\Struct\CategoryBasicCollection;
use Shopware\Holiday\Struct\HolidayBasicCollection;
use Shopware\PaymentMethod\Struct\PaymentMethodBasicCollection;
use Shopware\ShippingMethodPrice\Struct\ShippingMethodPriceBasicCollection;

class ShippingMethodDetailStruct extends ShippingMethodBasicStruct
{
    /**
     * @var string[]
     */
    protected $shippingMethodPriceUuids;
    /**
     * @var ShippingMethodPriceBasicCollection
     */
    protected $shippingMethodPrices;
    /**
     * @var string[]
     */
    protected $areaCountryUuids;
    /**
     * @var AreaCountryBasicCollection
     */
    protected $areaCountries;
    /**
     * @var string[]
     */
    protected $categoryUuids;
    /**
     * @var CategoryBasicCollection
     */
    protected $categories;
    /**
     * @var string[]
     */
    protected $holidayUuids;
    /**
     * @var HolidayBasicCollection
     */
    protected $holidaies;
    /**
     * @var string[]
     */
    protected $paymentMethodUuids;
    /**
     * @var PaymentMethodBasicCollection
     */
    protected $paymentMethods;

    public function __construct()
    {
        $this->shippingMethodPrices = new ShippingMethodPriceBasicCollection();
        $this->areaCountries = new AreaCountryBasicCollection();
        $this->categories = new CategoryBasicCollection();
        $this->holidaies = new HolidayBasicCollection();
        $this->paymentMethods = new PaymentMethodBasicCollection();
    }

    public function getShippingMethodPriceUuids(): array
    {
        return $this->shippingMethodPriceUuids;
    }

    public function setShippingMethodPriceUuids(array $shippingMethodPriceUuids): void
    {
        $this->shippingMethodPriceUuids = $shippingMethodPriceUuids;
    }

    public function getShippingMethodPrices(): ShippingMethodPriceBasicCollection
    {
        return $this->shippingMethodPrices;
    }

    public function setShippingMethodPrices(ShippingMethodPriceBasicCollection $shippingMethodPrices): void
    {
        $this->shippingMethodPrices = $shippingMethodPrices;
    }

    public function getAreaCountryUuids(): array
    {
        return $this->areaCountryUuids;
    }

    public function setAreaCountryUuids(array $areaCountryUuids): void
    {
        $this->areaCountryUuids = $areaCountryUuids;
    }

    public function getAreaCountries(): AreaCountryBasicCollection
    {
        return $this->areaCountries;
    }

    public function setAreaCountries(AreaCountryBasicCollection $areaCountries): void
    {
        $this->areaCountries = $areaCountries;
    }

    public function getCategoryUuids(): array
    {
        return $this->categoryUuids;
    }

    public function setCategoryUuids(array $categoryUuids): void
    {
        $this->categoryUuids = $categoryUuids;
    }

    public function getCategories(): CategoryBasicCollection
    {
        return $this->categories;
    }

    public function setCategories(CategoryBasicCollection $categories): void
    {
        $this->categories = $categories;
    }

    public function getHolidayUuids(): array
    {
        return $this->holidayUuids;
    }

    public function setHolidayUuids(array $holidayUuids): void
    {
        $this->holidayUuids = $holidayUuids;
    }

    public function getHolidaies(): HolidayBasicCollection
    {
        return $this->holidaies;
    }

    public function setHolidaies(HolidayBasicCollection $holidaies): void
    {
        $this->holidaies = $holidaies;
    }

    public function getPaymentMethodUuids(): array
    {
        return $this->paymentMethodUuids;
    }

    public function setPaymentMethodUuids(array $paymentMethodUuids): void
    {
        $this->paymentMethodUuids = $paymentMethodUuids;
    }

    public function getPaymentMethods(): PaymentMethodBasicCollection
    {
        return $this->paymentMethods;
    }

    public function setPaymentMethods(PaymentMethodBasicCollection $paymentMethods): void
    {
        $this->paymentMethods = $paymentMethods;
    }
}
