<?php
declare(strict_types=1);
/**
 * Shopware\Core 5
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
 * "Shopware\Core" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\Core\Framework\Struct;

class StructCollection extends Collection
{
    /**
     * @var Struct[]
     */
    protected $elements = [];

    public function add(Struct $struct, $key = null): void
    {
        if ($key !== null) {
            $this->elements[$key] = $struct;
        } else {
            $this->elements[] = $struct;
        }
    }

    public function fill(array $elements): void
    {
        foreach ($elements as $key => $element) {
            $this->add($element, $key);
        }
    }

    public function removeByKey($key): void
    {
        $this->doRemoveByKey($key);
    }

    public function get($key): ? Struct
    {
        return $this->elements[$key] ?? null;
    }
}
