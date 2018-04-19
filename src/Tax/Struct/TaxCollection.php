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

namespace Shopware\Tax\Struct;

use Shopware\Framework\Struct\Collection;

class TaxCollection extends Collection
{
    /**
     * @var Tax[]
     */
    protected $elements = [];

    public function add(Tax $tax): void
    {
        $key = $this->getKey($tax);
        $this->elements[$key] = $tax;
    }

    public function remove(int $id): void
    {
        parent::doRemoveByKey($id);
    }

    public function removeElement(Tax $tax): void
    {
        parent::doRemoveByKey($this->getKey($tax));
    }

    public function exists(Tax $tax): bool
    {
        return parent::has($this->getKey($tax));
    }

    public function get(int $id): ? Tax
    {
        if ($this->has($id)) {
            return $this->elements[$id];
        }

        return null;
    }

    protected function getKey(Tax $element): int
    {
        return $element->getId();
    }
}
