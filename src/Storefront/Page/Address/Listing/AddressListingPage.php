<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Address\Listing;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\Salutation\SalutationCollection;
use Shopware\Storefront\Page\Page;

#[Package('storefront')]
class AddressListingPage extends Page
{
    /**
     * @var CustomerAddressCollection
     */
    protected $addresses;

    /**
     * @var SalutationCollection
     */
    protected $salutations;

    /**
     * @var CountryCollection
     */
    protected $countries;

    /**
     * @var Cart
     */
    protected $cart;

    /**
     * @var CustomerAddressEntity|null
     */
    protected $address;

    public function getAddresses(): CustomerAddressCollection
    {
        return $this->addresses;
    }

    public function setAddresses(CustomerAddressCollection $addresses): void
    {
        $this->addresses = $addresses;
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

    public function getCart(): Cart
    {
        return $this->cart;
    }

    public function setCart(Cart $cart): void
    {
        $this->cart = $cart;
    }

    public function getAddress(): ?CustomerAddressEntity
    {
        return $this->address;
    }

    public function setAddress(?CustomerAddressEntity $address): void
    {
        $this->address = $address;
    }
}
