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

namespace Shopware\Address\Struct;

use Shopware\Country\Struct\CountryCollection;
use Shopware\CountryState\Struct\CountryStateCollection;
use Shopware\Framework\Struct\Collection;

class AddressCollection extends Collection
{
    /**
     * @var Address[]
     */
    protected $elements = [];

    public function add(Address $address): void
    {
        $key = $this->getKey($address);
        $this->elements[$key] = $address;
    }

    public function remove(int $id): void
    {
        parent::doRemoveByKey($id);
    }

    public function removeElement(Address $address): void
    {
        parent::doRemoveByKey($this->getKey($address));
    }

    public function exists(Address $address): bool
    {
        return parent::has($this->getKey($address));
    }

    public function get(int $id): ? Address
    {
        if ($this->has($id)) {
            return $this->elements[$id];
        }

        return null;
    }

    public function getCountries(): CountryCollection
    {
        return new CountryCollection(
            $this->map(function (Address $address) {
                return $address->getCountry();
            })
        );
    }

    public function getStates(): CountryStateCollection
    {
        $states = $this->map(function (Address $address) {
            return $address->getState();
        });

        return new CountryStateCollection(array_filter($states));
    }

    protected function getKey(Address $element): int
    {
        return $element->getId();
    }
}
