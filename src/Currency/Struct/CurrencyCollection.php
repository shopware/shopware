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

namespace Shopware\Currency\Struct;

use Shopware\Framework\Struct\Collection;

class CurrencyCollection extends Collection
{
    /**
     * @var Currency[]
     */
    protected $elements = [];

    public function add(Currency $currency): void
    {
        $key = $this->getKey($currency);
        $this->elements[$key] = $currency;
    }

    public function remove(int $id): void
    {
        parent::doRemoveByKey($id);
    }

    public function removeElement(Currency $currency): void
    {
        parent::doRemoveByKey($this->getKey($currency));
    }

    public function exists(Currency $currency): bool
    {
        return parent::has($this->getKey($currency));
    }

    public function get(int $id): ? Currency
    {
        if ($this->has($id)) {
            return $this->elements[$id];
        }

        return null;
    }

    protected function getKey(Currency $element): int
    {
        return $element->getId();
    }

    public function sortByPosition(): CurrencyCollection
    {
        $this->sort(function(Currency $a, Currency $b) {
            return $a->getPosition() <=> $b->getPosition();
        });
        return $this;
    }
}
