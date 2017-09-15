<?php declare(strict_types=1);
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

namespace Shopware\AreaCountry\Struct;

use Shopware\Framework\Struct\Collection;

class AreaCountryBasicCollection extends Collection
{
    /**
     * @var AreaCountryBasicStruct[]
     */
    protected $elements = [];

    public function add(AreaCountryBasicStruct $areaCountry): void
    {
        $key = $this->getKey($areaCountry);
        $this->elements[$key] = $areaCountry;
    }

    public function remove(string $uuid): void
    {
        parent::doRemoveByKey($uuid);
    }

    public function removeElement(AreaCountryBasicStruct $areaCountry): void
    {
        parent::doRemoveByKey($this->getKey($areaCountry));
    }

    public function exists(AreaCountryBasicStruct $areaCountry): bool
    {
        return parent::has($this->getKey($areaCountry));
    }

    public function getList(array $uuids): AreaCountryBasicCollection
    {
        return new self(array_intersect_key($this->elements, array_flip($uuids)));
    }

    public function get(string $uuid): ? AreaCountryBasicStruct
    {
        if ($this->has($uuid)) {
            return $this->elements[$uuid];
        }

        return null;
    }

    public function getUuids(): array
    {
        return $this->fmap(function (AreaCountryBasicStruct $areaCountry) {
            return $areaCountry->getUuid();
        });
    }

    public function getAreaUuids(): array
    {
        return $this->fmap(function (AreaCountryBasicStruct $areaCountry) {
            return $areaCountry->getAreaUuid();
        });
    }

    public function filterByAreaUuid(string $uuid): AreaCountryBasicCollection
    {
        return $this->filter(function (AreaCountryBasicStruct $areaCountry) use ($uuid) {
            return $areaCountry->getAreaUuid() === $uuid;
        });
    }

    protected function getKey(AreaCountryBasicStruct $element): string
    {
        return $element->getUuid();
    }
}
