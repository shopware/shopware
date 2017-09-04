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

namespace Shopware\Holiday\Struct;

use Shopware\Framework\Struct\Collection;

class HolidayBasicCollection extends Collection
{
    /**
     * @var HolidayBasicStruct[]
     */
    protected $elements = [];

    public function add(HolidayBasicStruct $holiday): void
    {
        $key = $this->getKey($holiday);
        $this->elements[$key] = $holiday;
    }

    public function remove(string $uuid): void
    {
        parent::doRemoveByKey($uuid);
    }

    public function removeElement(HolidayBasicStruct $holiday): void
    {
        parent::doRemoveByKey($this->getKey($holiday));
    }

    public function exists(HolidayBasicStruct $holiday): bool
    {
        return parent::has($this->getKey($holiday));
    }

    public function getList(array $uuids): HolidayBasicCollection
    {
        return new self(array_intersect_key($this->elements, array_flip($uuids)));
    }

    public function get(string $uuid): ? HolidayBasicStruct
    {
        if ($this->has($uuid)) {
            return $this->elements[$uuid];
        }

        return null;
    }

    public function getUuids(): array
    {
        return $this->fmap(function (HolidayBasicStruct $holiday) {
            return $holiday->getUuid();
        });
    }

    protected function getKey(HolidayBasicStruct $element): string
    {
        return $element->getUuid();
    }
}
