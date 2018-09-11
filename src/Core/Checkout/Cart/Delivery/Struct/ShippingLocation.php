<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Delivery\Struct;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressStruct;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateStruct;
use Shopware\Core\System\Country\CountryStruct;

class ShippingLocation extends Struct
{
    /**
     * @var CountryStruct
     */
    protected $country;

    /**
     * @var null|CountryStateStruct
     */
    protected $state;

    /**
     * @var null|CustomerAddressStruct
     */
    protected $address;

    public function __construct(CountryStruct $country, ?CountryStateStruct $state, ?CustomerAddressStruct $address)
    {
        $this->country = $country;
        $this->state = $state;
        $this->address = $address;
    }

    public static function createFromAddress(CustomerAddressStruct $address): self
    {
        return new self(
            $address->getCountry(),
            $address->getCountryState(),
            $address
        );
    }

    public static function createFromCountry(CountryStruct $country): self
    {
        return new self($country, null, null);
    }

    public function getCountry(): CountryStruct
    {
        if ($this->address) {
            return $this->address->getCountry();
        }

        return $this->country;
    }

    public function getState(): ?CountryStateStruct
    {
        if ($this->address) {
            return $this->address->getCountryState();
        }

        return $this->state;
    }

    public function getAddress(): ?CustomerAddressStruct
    {
        return $this->address;
    }
}
