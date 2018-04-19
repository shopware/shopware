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

namespace Shopware\CustomerGroup\Struct;

use Shopware\Framework\Struct\Collection;

class CustomerGroupCollection extends Collection
{
    /**
     * @var CustomerGroup[]
     */
    protected $elements = [];

    public function add(CustomerGroup $customerGroup): void
    {
        $key = $this->getKey($customerGroup);
        $this->elements[$key] = $customerGroup;
    }

    public function remove(int $id): void
    {
        parent::doRemoveByKey($id);
    }

    public function removeElement(CustomerGroup $customerGroup): void
    {
        parent::doRemoveByKey($this->getKey($customerGroup));
    }

    public function exists(CustomerGroup $customerGroup): bool
    {
        return parent::has($this->getKey($customerGroup));
    }

    public function get(int $id): ? CustomerGroup
    {
        if ($this->has($id)) {
            return $this->elements[$id];
        }

        return null;
    }

    public function getByKey(string $key): ? CustomerGroup
    {
        foreach ($this->elements as $element) {
            if ($element->getKey() === $key) {
                return $element;
            }
        }

        return null;
    }

    protected function getKey(CustomerGroup $element): int
    {
        return $element->getId();
    }
}
