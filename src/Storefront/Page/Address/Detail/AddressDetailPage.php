<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Address\Detail;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\Salutation\SalutationCollection;
use Shopware\Storefront\Page\Page;

#[Package('storefront')]
class AddressDetailPage extends Page
{
    /**
     * @var CustomerAddressEntity|null
     */
    protected $address;

    /**
     * @var SalutationCollection
     */
    protected $salutations;

    /**
     * @var CountryCollection
     */
    protected $countries;

    public function getAddress(): ?CustomerAddressEntity
    {
        return $this->address;
    }

    public function setAddress(?CustomerAddressEntity $address): void
    {
        $this->address = $address;
    }

    public function getSalutations(): SalutationCollection
    {
        return $this->salutations;
    }

    public function setSalutations(SalutationCollection $salutations): void
    {
        $this->salutations = $salutations;
    }

    public function getCountries(): CountryCollection
    {
        return $this->countries;
    }

    public function setCountries(CountryCollection $countries): void
    {
        $this->countries = $countries;
    }
}
