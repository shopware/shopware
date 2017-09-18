<?php declare(strict_types=1);

namespace Shopware\PaymentMethod\Struct;

use Shopware\AreaCountry\Struct\AreaCountryBasicCollection;
use Shopware\Shop\Struct\ShopBasicCollection;

class PaymentMethodDetailStruct extends PaymentMethodBasicStruct
{
    /**
     * @var string[]
     */
    protected $shopUuids = [];

    /**
     * @var ShopBasicCollection
     */
    protected $shops;

    /**
     * @var string[]
     */
    protected $countryUuids = [];

    /**
     * @var AreaCountryBasicCollection
     */
    protected $countries;

    public function __construct()
    {
        $this->shops = new ShopBasicCollection();
        $this->countries = new AreaCountryBasicCollection();
    }

    public function getShopUuids(): array
    {
        return $this->shopUuids;
    }

    public function setShopUuids(array $shopUuids): void
    {
        $this->shopUuids = $shopUuids;
    }

    public function getShops(): ShopBasicCollection
    {
        return $this->shops;
    }

    public function setShops(ShopBasicCollection $shops): void
    {
        $this->shops = $shops;
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
}
