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

namespace Shopware\CountryArea\Struct;

use Shopware\Framework\Struct\Collection;

class CountryAreaCollection extends Collection
{
    /**
     * @var CountryArea[]
     */
    protected $elements = [];

    public function add(CountryArea $countryArea): void
    {
        $key = $this->getKey($countryArea);
        $this->elements[$key] = $countryArea;
    }

    public function remove(int $id): void
    {
        parent::doRemoveByKey($id);
    }

    public function removeElement(CountryArea $countryArea): void
    {
        parent::doRemoveByKey($this->getKey($countryArea));
    }

    public function exists(CountryArea $countryArea): bool
    {
        return parent::has($this->getKey($countryArea));
    }

    public function get(int $id): ? CountryArea
    {
        if ($this->has($id)) {
            return $this->elements[$id];
        }

        return null;
    }

    protected function getKey(CountryArea $element): int
    {
        return $element->getId();
    }
}
