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

namespace Shopware\Unit\Struct;

use Shopware\Framework\Struct\Collection;

class UnitBasicCollection extends Collection
{
    /**
     * @var UnitBasicStruct[]
     */
    protected $elements = [];

    public function add(UnitBasicStruct $unit): void
    {
        $key = $this->getKey($unit);
        $this->elements[$key] = $unit;
    }

    public function remove(string $uuid): void
    {
        parent::doRemoveByKey($uuid);
    }

    public function removeElement(UnitBasicStruct $unit): void
    {
        parent::doRemoveByKey($this->getKey($unit));
    }

    public function exists(UnitBasicStruct $unit): bool
    {
        return parent::has($this->getKey($unit));
    }

    public function getList(array $uuids): UnitBasicCollection
    {
        return new self(array_intersect_key($this->elements, array_flip($uuids)));
    }

    public function get(string $uuid): ? UnitBasicStruct
    {
        if ($this->has($uuid)) {
            return $this->elements[$uuid];
        }

        return null;
    }

    public function getUuids(): array
    {
        return $this->fmap(
            function (UnitBasicStruct $unit) {
                return $unit->getUuid();
            }
        );
    }

    protected function getKey(UnitBasicStruct $element): string
    {
        return $element->getUuid();
    }
}
