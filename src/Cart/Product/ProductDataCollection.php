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

namespace Shopware\Cart\Product;

use Shopware\Framework\Struct\Collection;

class ProductDataCollection extends Collection
{
    /**
     * @var ProductData[]
     */
    protected $elements = [];

    public function add(ProductData $data): void
    {
        $this->elements[$this->getKey($data)] = $data;
    }

    public function remove(string $identifier): void
    {
        parent::doRemoveByKey($identifier);
    }

    public function removeElement(ProductData $data): void
    {
        parent::doRemoveByKey($this->getKey($data));
    }

    public function get(string $number): ? ProductData
    {
        if ($this->has($number)) {
            return $this->elements[$number];
        }

        return null;
    }

    protected function getKey(ProductData $element): string
    {
        return $element->getNumber();
    }
}
