<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\Address;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Storefront\Framework\Page\PageWithHeader;

class AccountAddressPage extends PageWithHeader
{
    /**
     * @var CountryCollection
     */
    protected $countries;

    /**
     * @var CustomerAddressEntity|null
     */
    protected $address;

    public function getCountries(): CountryCollection
    {
        return $this->countries;
    }

    public function setCountries(CountryCollection $countries): void
    {
        $this->countries = $countries;
    }

    public function getAddress(): ?CustomerAddressEntity
    {
        return $this->address;
    }

    public function setAddress(CustomerAddressEntity $address): void
    {
        $this->address = $address;
    }
}
