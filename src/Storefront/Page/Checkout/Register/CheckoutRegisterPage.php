<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Checkout\Register;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\Salutation\SalutationCollection;
use Shopware\Storefront\Page\Page;

#[Package('storefront')]
class CheckoutRegisterPage extends Page
{
    /**
     * @var CountryCollection
     */
    protected $countries;

    /**
     * @var CustomerAddressEntity|null
     */
    protected $address;

    /**
     * @var SalutationCollection
     */
    protected $salutations;

    /**
     * @var Cart
     */
    protected $cart;

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

    public function getSalutations(): SalutationCollection
    {
        return $this->salutations;
    }

    public function setSalutations(SalutationCollection $salutations): void
    {
        $this->salutations = $salutations;
    }

    public function getCart(): Cart
    {
        return $this->cart;
    }

    public function setCart(Cart $cart): void
    {
        $this->cart = $cart;
    }
}
