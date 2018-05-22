<?php
declare(strict_types=1);
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

namespace Shopware\Checkout\Cart\Delivery\Struct;

use Shopware\Checkout\Customer\Aggregate\CustomerAddress\Struct\CustomerAddressBasicStruct;
use Shopware\Framework\Struct\Struct;
use Shopware\System\Country\Aggregate\CountryState\Struct\CountryStateBasicStruct;
use Shopware\System\Country\Struct\CountryBasicStruct;

class ShippingLocation extends Struct
{
    /**
     * @var CountryBasicStruct
     */
    protected $country;

    /**
     * @var null|CountryStateBasicStruct
     */
    protected $state;

    /**
     * @var null|\Shopware\Checkout\Customer\Aggregate\CustomerAddress\Struct\CustomerAddressBasicStruct
     */
    protected $address;

    public function __construct(CountryBasicStruct $country, ?CountryStateBasicStruct $state, ?CustomerAddressBasicStruct $address)
    {
        $this->country = $country;
        $this->state = $state;
        $this->address = $address;
    }

    public static function createFromAddress(CustomerAddressBasicStruct $address): self
    {
        return new self(
            $address->getCountry(),
            $address->getCountryState(),
            $address
        );
    }

    public static function createFromCountry(CountryBasicStruct $country): self
    {
        return new self($country, null, null);
    }

    public function getCountry(): CountryBasicStruct
    {
        if ($this->address) {
            return $this->address->getCountry();
        }

        return $this->country;
    }

    public function getState(): ?CountryStateBasicStruct
    {
        if ($this->address) {
            return $this->address->getCountryState();
        }

        return $this->state;
    }

    public function getAddress(): ?CustomerAddressBasicStruct
    {
        return $this->address;
    }

    public function getAreaId(): string
    {
        return $this->getCountry()->getAreaId();
    }
}
