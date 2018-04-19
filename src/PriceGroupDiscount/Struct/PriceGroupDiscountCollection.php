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

namespace Shopware\PriceGroupDiscount\Struct;

use Shopware\Framework\Struct\Collection;

class PriceGroupDiscountCollection extends Collection
{
    /**
     * @var PriceGroupDiscount[]
     */
    protected $elements = [];

    public function add(PriceGroupDiscount $priceGroupDiscount): void
    {
        $key = $this->getKey($priceGroupDiscount);
        $this->elements[$key] = $priceGroupDiscount;
    }

    public function remove(int $id): void
    {
        parent::doRemoveByKey($id);
    }

    public function removeElement(PriceGroupDiscount $priceGroupDiscount): void
    {
        parent::doRemoveByKey($this->getKey($priceGroupDiscount));
    }

    public function exists(PriceGroupDiscount $priceGroupDiscount): bool
    {
        return parent::has($this->getKey($priceGroupDiscount));
    }

    public function get(int $id): ? PriceGroupDiscount
    {
        if ($this->has($id)) {
            return $this->elements[$id];
        }

        return null;
    }

    protected function getKey(PriceGroupDiscount $element): int
    {
        return $element->getId();
    }
}
