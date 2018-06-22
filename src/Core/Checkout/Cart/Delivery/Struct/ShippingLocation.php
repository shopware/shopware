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

    public function getAreaId(): string
    {
        return $this->getCountry()->getAreaId();
    }
}
