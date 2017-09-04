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

namespace Shopware\Currency\Struct;

use Shopware\Framework\Struct\Collection;

class CurrencyBasicCollection extends Collection
{
    /**
     * @var CurrencyBasicStruct[]
     */
    protected $elements = [];

    public function add(CurrencyBasicStruct $currency): void
    {
        $key = $this->getKey($currency);
        $this->elements[$key] = $currency;
    }

    public function remove(string $uuid): void
    {
        parent::doRemoveByKey($uuid);
    }

    public function removeElement(CurrencyBasicStruct $currency): void
    {
        parent::doRemoveByKey($this->getKey($currency));
    }

    public function exists(CurrencyBasicStruct $currency): bool
    {
        return parent::has($this->getKey($currency));
    }

    public function getList(array $uuids): CurrencyBasicCollection
    {
        return new self(array_intersect_key($this->elements, array_flip($uuids)));
    }

    public function get(string $uuid): ? CurrencyBasicStruct
    {
        if ($this->has($uuid)) {
            return $this->elements[$uuid];
        }

        return null;
    }

    public function getUuids(): array
    {
        return $this->fmap(
            function (CurrencyBasicStruct $currency) {
                return $currency->getUuid();
            }
        );
    }

    protected function getKey(CurrencyBasicStruct $element): string
    {
        return $element->getUuid();
    }
}
