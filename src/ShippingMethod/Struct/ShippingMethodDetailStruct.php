<?php declare(strict_types=1);

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
    protected $categoryUuids = [];

    /**
     * @var CategoryBasicCollection
     */
    protected $categories;

    /**
     * @var string[]
     */
    protected $countryUuids = [];

    /**
     * @var AreaCountryBasicCollection
     */
    protected $countries;

    /**
     * @var string[]
     */
    protected $holidayUuids = [];

    /**
     * @var HolidayBasicCollection
     */
    protected $holidays;

    /**
     * @var string[]
     */
    protected $paymentMethodUuids = [];

    /**
     * @var PaymentMethodBasicCollection
     */
    protected $paymentMethods;

    /**
     * @var ShippingMethodPriceBasicCollection
     */
    protected $prices;

    public function __construct()
    {
        $this->categories = new CategoryBasicCollection();
        $this->countries = new AreaCountryBasicCollection();
        $this->holidays = new HolidayBasicCollection();
        $this->paymentMethods = new PaymentMethodBasicCollection();
        $this->prices = new ShippingMethodPriceBasicCollection();
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

    public function getCountryUuids(): array
    {
        return $this->countryUuids;
    }

    public function setCountryUuids(array $countryUuids): void
    {
        $this->countryUuids = $countryUuids;
    }

    public function getCountries(): AreaCountryBasicCollection
    {
        return $this->countries;
    }

    public function setCountries(AreaCountryBasicCollection $countries): void
    {
        $this->countries = $countries;
    }

    public function getHolidayUuids(): array
    {
        return $this->holidayUuids;
    }

    public function setHolidayUuids(array $holidayUuids): void
    {
        $this->holidayUuids = $holidayUuids;
    }

    public function getHolidays(): HolidayBasicCollection
    {
        return $this->holidays;
    }

    public function setHolidays(HolidayBasicCollection $holidays): void
    {
        $this->holidays = $holidays;
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

    public function getPrices(): ShippingMethodPriceBasicCollection
    {
        return $this->prices;
    }

    public function setPrices(ShippingMethodPriceBasicCollection $prices): void
    {
        $this->prices = $prices;
    }
}
