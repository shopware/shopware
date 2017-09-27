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

namespace Shopware\Framework\Write\Field;

use Shopware\Framework\Write\Flag\Flag;

abstract class Field
{
    /**
     * @var Flag[]
     */
    private $flags = [];

    /**
     * @param string $type
     * @param string $key
     * @param null   $value
     *
     * @return \Generator
     */
    abstract public function __invoke(string $type, string $key, $value = null): \Generator;

    /**
     * @param Flag[] ...$flags
     *
     * @return self
     */
    public function setFlags(Flag  ...$flags): Field
    {
        $this->flags = $flags;

        return $this;
    }

    /**
     * @param string $class
     *
     * @return bool
     */
    public function is(string $class): bool
    {
        foreach ($this->flags as $flag) {
            if ($flag instanceof $class) {
                return true;
            }
        }

        return false;
    }
}
