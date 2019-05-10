<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\AddressBook;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\Salutation\SalutationCollection;
use Shopware\Storefront\Framework\Page\StorefrontSearchResult;

class AddressBookPagelet extends Struct
{
    /** @var CustomerAddressEntity|null */
    private $address;

    /** @var StorefrontSearchResult|null */
    private $addresses;

    /** @var SalutationCollection */
    private $salutations;

    /** @var CountryCollection */
    private $countries;

    /** @var CustomerEntity */
    private $customer;

    public function __construct(SalutationCollection $salutations, CountryCollection $countries, CustomerEntity $customer)
    {
        $this->address = null;
        $this->addresses = null;
        $this->salutations = $salutations;
        $this->countries = $countries;
        $this->customer = $customer;
    }

    public function setAddresses(StorefrontSearchResult $addresses)
    {
        $this->addresses = $addresses;
    }

    public function getAddresses(): ?StorefrontSearchResult
    {
        return $this->addresses;
    }

    public function getSalutations(): SalutationCollection
    {
        return $this->salutations;
    }

    public function getCountries(): CountryCollection
    {
        return $this->countries;
    }

    public function setAddress(CustomerAddressEntity $address)
    {
        $this->address = $address;
    }

    public function getAddress(): ?CustomerAddressEntity
    {
        return $this->address;
    }

    public function getCustomer(): CustomerEntity
    {
        return $this->customer;
    }

    public function setCustomer(CustomerEntity $customer): void
    {
        $this->customer = $customer;
    }
}
