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

namespace Shopware\Cart\Delivery;

use Shopware\Address\Struct\Address;
use Shopware\Country\Struct\CountryIdentity;
use Shopware\CountryState\Struct\CountryState;
use Shopware\Framework\Struct\Struct;

class ShippingLocation extends Struct
{
    /**
     * @var CountryIdentity
     */
    protected $country;

    /**
     * @var null|CountryState
     */
    protected $state;

    /**
     * @var null|Address
     */
    protected $address;

    /**
     * @param CountryIdentity $country
     * @param null|CountryState $state
     * @param null|Address $address
     */
    public function __construct(CountryIdentity $country, ?CountryState $state, ?Address $address)
    {
        $this->country = $country;
        if ($state) {
            $this->country = $state->getCountry();
        }

        $this->state = $state;
        $this->address = $address;
    }

    public static function createFromAddress(Address $address): ShippingLocation
    {
        return new self(
            $address->getCountry(),
            $address->getState(),
            $address
        );
    }

    public static function createFromState(CountryState $state): ShippingLocation
    {
        return new self(
            $state->getCountry(),
            $state,
            null
        );
    }

    public static function createFromCountry(CountryIdentity $country): ShippingLocation
    {
        return new self(
            $country,
            null,
            null
        );
    }

    public function getCountry(): CountryIdentity
    {
        if ($this->address) {
            return $this->address->getCountry();
        }

        return $this->country;
    }

    public function getState(): ?CountryState
    {
        if ($this->address) {
            return $this->address->getState();
        }

        return $this->state;
    }

    public function getAddress(): ?Address
    {
        return $this->address;
    }

    public function getAreaId(): int
    {
        return $this->getCountry()->getAreaId();
    }
}
